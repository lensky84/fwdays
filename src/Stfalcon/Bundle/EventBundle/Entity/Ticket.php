<?php

namespace Stfalcon\Bundle\EventBundle\Entity;

use Application\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Stfalcon\Bundle\PaymentBundle\Entity\Payment;

/**
 * Stfalcon\Bundle\EventBundle\Entity\Ticket
 *
 * @ORM\Table(name="event__tickets")
 * @ORM\Entity(repositoryClass="Stfalcon\Bundle\EventBundle\Repository\TicketRepository")
 */
class Ticket
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Сумма для оплаты
     *
     * @var float $amount
     *
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=2)
     */
    private $amount;

    /**
     * Сумма без учета скидки
     *
     * @var float $amountWithoutDiscount
     *
     * @ORM\Column(name="amount_without_discount", type="decimal", precision=10, scale=2)
     */
    private $amountWithoutDiscount;

    /**
     * @var PromoCode
     *
     * @ORM\ManyToOne(targetEntity="PromoCode")
     * @ORM\JoinColumn(name="promo_code_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $promoCode;

    /**
     * @var Event
     *
     * @ORM\ManyToOne(targetEntity="Event")
     * @ORM\JoinColumn(name="event_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $event;

    /**
     * На кого выписан билет. Т.е. участник не обязательно плательщик
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="Application\Bundle\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $user;

    /**
     * @var Stfalcon\Bundle\PaymentBundle\Entity\Payment
     *
     * @ORM\ManyToOne(targetEntity="Stfalcon\Bundle\PaymentBundle\Entity\Payment", inversedBy="tickets")
     * @ORM\JoinColumn(name="payment_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $payment;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     *
     * @ORM\Column(name="updated_at", type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    /**
     * @var boolean $used
     * @ORM\Column(name="used", type="boolean")
     */
    private $used = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="has_discount", type="boolean")
     */
    private $hasDiscount = false;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param Event $event
     *
     * @return void
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;
    }

    /**
     * @return Payment
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param Payment|null $payment
     *
     * @return void
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Checking if ticket is "paid"
     *
     * @return bool
     */
    public function isPaid()
    {
        return (bool) ($this->getPayment() != null && $this->getPayment()->isPaid());
    }

    /**
     * Mark ticket as "used"
     *
     * @param bool $used
     */
    public function setUsed($used){
        $this->used = $used;
    }

    /**
     * Checking if ticket is "used"
     *
     * @return bool
     */
    public function isUsed()
    {
        return $this->used;
    }

    /**
     * Generate unique md5 hash for ticket
     * @return string
     */
    public function getHash()
    {
        return md5($this->getId() . $this->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getId() . ' (' . $this->getUser()->getFullname() . ')';
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amountWithoutDiscount
     */
    public function setAmountWithoutDiscount($amountWithoutDiscount)
    {
        $this->amountWithoutDiscount = $amountWithoutDiscount;
    }

    /**
     * @return float
     */
    public function getAmountWithoutDiscount()
    {
        return $this->amountWithoutDiscount;
    }

    /**
     * @param \Stfalcon\Bundle\EventBundle\Entity\PromoCode $promoCode
     */
    public function setPromoCode($promoCode)
    {
        $this->setHasDiscount(true);
        $amountWithDiscount = $this->amountWithoutDiscount - ($this->amountWithoutDiscount * ($promoCode->getDiscountAmount() / 100));
        $this->setAmount($amountWithDiscount);
        $this->promoCode = $promoCode;
    }

    /**
     * @return \Stfalcon\Bundle\EventBundle\Entity\PromoCode
     */
    public function getPromoCode()
    {
        return $this->promoCode;
    }

    /**
     * @return bool
     */
    public function hasPromoCode()
    {
        return !empty($this->promoCode);
    }

    /**
     * @param boolean $hasDiscount
     */
    public function setHasDiscount($hasDiscount)
    {
        $this->hasDiscount = $hasDiscount;
    }

    /**
     * @return boolean
     */
    public function getHasDiscount()
    {
        return $this->hasDiscount;
    }
}
