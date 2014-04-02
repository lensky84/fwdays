<?php

namespace Stfalcon\Bundle\PaymentBundle\Controller;

use Stfalcon\Bundle\EventBundle\Entity\PromoCode;
use Stfalcon\Bundle\EventBundle\Entity\Ticket;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use JMS\SecurityExtraBundle\Annotation\Secure;
use Stfalcon\Bundle\PaymentBundle\Form\Payments\Interkassa\PayType;
use Stfalcon\Bundle\PaymentBundle\Entity\Payment;
use Stfalcon\Bundle\EventBundle\Entity\Event;
use Application\Bundle\UserBundle\Entity\User;
use Stfalcon\Bundle\EventBundle\Entity\Mail;
use Stfalcon\Bundle\PaymentBundle\Service\IntercassaService;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class InterkassaController
 */
class InterkassaController extends Controller
{
    /**
     * @param Event     $event             Event
     * @param User      $user              User
     * @param Payment   $payment           Payment
     * @param FormView  $promoCodeFormView Promo code form view
     * @param PromoCode $promoCode         Promo code
     * @param FormView  $ticketFormView    Ticket form view
     *
     * @return array
     *
     * @Template()
     * @Secure(roles="ROLE_USER")
     */
    public function payAction($event, $user, $payment, $promoCodeFormView, $promoCode, $ticketFormView)
    {
        $config = $this->container->getParameter('stfalcon_payment.config');

        $description = 'Оплата участия в конференции ' . $event->getName()
                       . '. Плательщик ' . $user->getFullname() . ' (#' . $user->getId() . ')';

        /** @var IntercassaService $intercassa */
        $intercassa = $this->container->get('stfalcon_payment.intercassa.service');

        $params['ik_co_id'] = $config['interkassa']['shop_id'];
        $params['ik_am']    = $payment->getAmount();
        $params['ik_pm_no'] = $payment->getId();
        $params['ik_desc']  = $description;

        $data = array(
            'ik_co_id' => $config['interkassa']['shop_id'],
            'ik_desc'  => $description,
            'ik_sign'  => $intercassa->getSignHash($params)
        );

        return array(
            'data'          => $data,
            'event'         => $event,
            'payment'       => $payment,
            'promoCodeForm' => $promoCodeFormView,
            'promoCode'     => $promoCode,
            'ticketForm'    => $ticketFormView
        );
    }

    /**
     * Принимает ответ от шлюзапосле оплаты и показывает страницу с резлльтатом оллаты пользователю
     *
     * @param Request $request
     *
     * @return array
     *
     * @Route("/payments/interkassa/status")
     */
    public function statusAction(Request $request)
    {
        /** @var \Stfalcon\Bundle\PaymentBundle\Entity\Payment $payment */
        $payment = $this->getDoctrine()
                     ->getRepository('StfalconPaymentBundle:Payment')
                     ->findOneBy(array('id' => $request->get('ik_pm_no')));

        if ($payment->getStatus() == Payment::STATUS_PAID) {
            $resultMessage = 'Проверка контрольной подписи данных о платеже успешно пройдена!';
        } else {
            $resultMessage = 'Проверка контрольной подписи данных о платеже провалена!';
        }

        return $this->render('StfalconPaymentBundle:Payment:success.html.twig', array(
            'message' => $resultMessage
        ));
    }

    /**
     * Принимает ответ от шлюза и изменяет статус платежа
     *
     * @param Request $request
     *
     * @return array
     *
     * @Route("/payments/interkassa/change-status")
     * @Method({"POST"})
     */
    public function changeStatusAction(Request $request)
    {
        /** @var Payment $payment */
        $payment = $this->getDoctrine()
            ->getRepository('StfalconPaymentBundle:Payment')
            ->findOneBy(array('id' => $request->get('ik_pm_no')));

        /** Если платеж не найден */
        if (!$payment instanceof Payment) {
            return new Response('ik_pm_no not found', 404);
        }
        /** @var IntercassaService $intercassa */
        $intercassa = $this->container->get('stfalcon_payment.intercassa.service');
        /** Выбераем все параментры которые нам прислала Интеркасса */
        $params = $request->request->all();
        /** Исключаем из параментров подпись */
        unset($params['ik_sign']);
        /** Генерируем подпись для проверки данных */
        $signHash = $intercassa->getSignHash($params);
        $config = $this->container->getParameter('stfalcon_payment.config');

        if ($payment->getStatus() == Payment::STATUS_PENDING &&
            $request->get('ik_sign') == $signHash &&
            $request->get('ik_inv_st') == 'success' &&
            $request->get('ik_co_id') == $config['interkassa']['shop_id'] &&
            $request->get('ik_am') == $payment->getAmount()
        ) {
            $payment->setStatus(Payment::STATUS_PAID);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            /** @var Ticket  $ticket */
            foreach ($payment->getTickets() as $ticket) {
                $user  = $ticket->getUser();
                $event = $ticket->getEvent();

                $twig = $this->get('twig');

                $successPaymentTemplateContent = $twig->loadTemplate('StfalconEventBundle:Interkassa:success_payment.html.twig')
                    ->render(array(
                            'event_slug' => $event->getSlug()
                        ));

                $mail = new Mail();
                $mail->addEvent($event);
                $mail->setText($successPaymentTemplateContent);

                // Get base template for email
                $emailTemplateContent = $twig->loadTemplate('StfalconEventBundle::email.html.twig');

                $dateFormatter = new \IntlDateFormatter(
                    'ru-RU',
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    date_default_timezone_get(),
                    \IntlDateFormatter::GREGORIAN,
                    'd MMMM Y'
                );

                $text = $mail->replace(
                    array(
                        '%fullname%' => $user->getFullName(),
                        '%event%'    => $event->getName(),
                        '%date%'     => $dateFormatter->format($event->getDate()),
                        '%place%'    => $event->getPlace(),
                    )
                );

                $body = $emailTemplateContent->render(array(
                        'text'               => $text,
                        'mail'               => $mail,
                        'add_bottom_padding' => true
                    ));

                /** @var $pdfGen \Stfalcon\Bundle\EventBundle\Helper\PdfGeneratorHelper */
                $pdfGen = $this->get('stfalcon_event.pdf_generator.helper');
                $html = $pdfGen->generateHTML($ticket);
                $outputFile = 'ticket-' . $event->getSlug() . '.pdf';

                $message = \Swift_Message::newInstance()
                    ->setSubject($event->getName())
                    ->setFrom('orgs@fwdays.com', 'Frameworks Days')
                    ->setTo($user->getEmail())
                    ->setBody($body, 'text/html')
                    ->attach(\Swift_Attachment::newInstance($pdfGen->generatePdfFile($html, $outputFile)));

                $this->get('mailer')->send($message);
            }

            return new Response('OK');
        }

        return new Response('One of the parameters is bad', 400);
    }
}
