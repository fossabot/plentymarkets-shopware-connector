<?php

namespace ShopwareAdapter\ResponseParser\OrderItem;

use Doctrine\ORM\EntityManagerInterface;
use PlentyConnector\Connector\IdentityService\IdentityServiceInterface;
use PlentyConnector\Connector\TransferObject\Order\OrderItem\OrderItem;
use PlentyConnector\Connector\TransferObject\VatRate\VatRate;
use PlentymarketsAdapter\ResponseParser\GetAttributeTrait;
use ShopwareAdapter\ResponseParser\OrderItem\Exception\UnsupportedVatRateException;
use ShopwareAdapter\ShopwareAdapter;

/**
 * Class OrderItemResponseParser
 */
class OrderItemResponseParser implements OrderItemResponseParserInterface
{
    use GetAttributeTrait;

    /**
     * @var IdentityServiceInterface
     */
    private $identityService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * OrderItemResponseParser constructor.
     *
     * @param IdentityServiceInterface $identityService
     * @param EntityManagerInterface   $entityManager
     */
    public function __construct(
        IdentityServiceInterface $identityService,
        EntityManagerInterface $entityManager
    ) {
        $this->identityService = $identityService;
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(array $entry, $taxFree = false)
    {
        switch ($entry['mode']) {
            case 0:
            case 1:
                return $this->handleProduct($entry, $taxFree);

                break;
            case 2:
                return $this->handleVoucher($entry, $taxFree);

                break;
            case 3:
                return $this->handleDiscount($entry, $taxFree);

                break;
            case 4:
                return $this->handlePaymentSurcharge($entry, $taxFree);

                break;
            default:
                throw new \Exception('unsupported entry mode');
        }
    }

    /**
     * @param array $entry
     *
     * @throws UnsupportedVatRateException
     *
     * @return null|string
     */
    private function getVatRateIdentifier(array $entry)
    {
        if (0 === (int) $entry['taxId'] && 0 === (int) $entry['taxRate']) {
            return null;
        }

        $vatRateIdentity = $this->identityService->findOneBy([
            'adapterIdentifier' => (string) $entry['taxId'],
            'adapterName' => ShopwareAdapter::NAME,
            'objectType' => VatRate::TYPE,
        ]);

        if (null === $vatRateIdentity) {
            throw new UnsupportedVatRateException();
        }

        return $vatRateIdentity->getObjectIdentifier();
    }

    /**
     * @param array $entry
     * @param bool  $taxFree
     *
     * @return OrderItem
     */
    private function handleProduct(array $entry, $taxFree = false)
    {
        /**
         * @var OrderItem $orderItem
         */
        $orderItem = OrderItem::fromArray([
            'type' => OrderItem::TYPE_PRODUCT,
            'quantity' => (float) $entry['quantity'],
            'name' => $entry['articleName'],
            'number' => $entry['articleNumber'],
            'price' => (float) $entry['price'],
            'vatRateIdentifier' => $taxFree ? null : $this->getVatRateIdentifier($entry),
            'attributes' => $this->getAttributes($entry['attribute']),
        ]);

        return $orderItem;
    }

    /**
     * @param array $entry
     * @param bool  $taxFree
     *
     * @return OrderItem
     */
    private function handleDiscount(array $entry, $taxFree = false)
    {
        /**
         * @var OrderItem $orderItem
         */
        $orderItem = OrderItem::fromArray([
            'type' => OrderItem::TYPE_DISCOUNT,
            'quantity' => (float) $entry['quantity'],
            'name' => $entry['articleName'],
            'number' => $entry['articleNumber'],
            'price' => (float) $entry['price'],
            'vatRateIdentifier' => $taxFree ? null : $this->getVatRateIdentifier($entry),
            'attributes' => $this->getAttributes($entry['attribute']),
        ]);

        return $orderItem;
    }

    /**
     * @param array $entry
     * @param bool  $taxFree
     *
     * @return OrderItem
     */
    private function handlePaymentSurcharge(array $entry, $taxFree = false)
    {
        /**
         * @var OrderItem $orderItem
         */
        $orderItem = OrderItem::fromArray([
            'type' => OrderItem::TYPE_PAYMENT_SURCHARGE,
            'quantity' => (float) $entry['quantity'],
            'name' => $entry['articleName'],
            'number' => $entry['articleNumber'],
            'price' => (float) $entry['price'],
            'vatRateIdentifier' => $taxFree ? null : $this->getVatRateIdentifier($entry),
            'attributes' => $this->getAttributes($entry['attribute']),
        ]);

        return $orderItem;
    }

    /**
     * @param array $entry
     * @param bool  $taxFree
     *
     * @return OrderItem
     */
    private function handleVoucher(array $entry, $taxFree = false)
    {
        /**
         * @var OrderItem $orderItem
         */
        $orderItem = OrderItem::fromArray([
            'type' => OrderItem::TYPE_VOUCHER,
            'quantity' => (float) $entry['quantity'],
            'name' => $entry['articleName'],
            'number' => $entry['articleNumber'],
            'price' => (float) $entry['price'],
            'vatRateIdentifier' => $taxFree ? null : $this->getVatRateIdentifier($entry),
            'attributes' => $this->getAttributes($entry['attribute']),
        ]);

        return $orderItem;
    }
}
