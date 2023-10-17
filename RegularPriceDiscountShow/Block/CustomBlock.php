<?php
declare(strict_types=1);

namespace RltSquare\RegularPriceDiscountShow\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use RltSquare\RegularPriceDiscountShow\Logger\Logger;

/**
 * @class CustomBlock
 */
class CustomBlock extends Template
{

    protected CheckoutSession $checkoutSession;

    protected Context $context;

    protected ProductRepositoryInterface $productRepository;

    protected array $data;
    private Logger $logger;


    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        Context                    $context,
        CheckoutSession            $checkoutSession,
        ProductRepositoryInterface $productRepository,
        Logger                     $logger,
        array                      $data = []
    )
    {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->productRepository = $productRepository;
        $this->data = $data;
        $this->logger = $logger;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getDiscountedPrice(): float
    {
        $discountAmount = 0;

        $cartData = $this->checkoutSession->getQuote()->getAllItems();
        if ($cartData) {
            foreach ($cartData as $item) {
                $productId = $item->getProductId();
                $product = $this->productRepository->getById($productId);

                if ($product) {
                    $price = $product->getPrice();
                    $productName = $product->getName();
                    $specialPrice = $product->getSpecialPrice();
                    $specialFromDate = $product->getSpecialFromDate(); // Get the special price start date
                    $specialToDate = $product->getSpecialToDate(); // Get the special price end date
                    $today = time();
                    if (!$specialPrice)
                        $specialPrice = $price;
                    // Check if the special price is valid and within the date range
                    if ($specialPrice > 0 && $specialPrice < $price) {
                        if ((is_null($specialFromDate) && is_null($specialToDate)) || ($today >= strtotime($specialFromDate) && is_null($specialToDate)) || ($today <= strtotime($specialToDate) && is_null($specialFromDate)) || ($today >= strtotime($specialFromDate) && $today <= strtotime($specialToDate))) {
                            $discountAmount += ($price - $specialPrice) * $item->getQty();
                            $this->logger->notice('Discount price for ' . $productName . ' is ' . $discountAmount);
                        }
                    } else {
                        $this->logger->warning('Regular Price for ' . $productName . ' does not apply on this product');
                    }
                } else {
                    $this->logger->warning('Product ' . $productId . ' does not exist');
                }
            }
        } else {
            $this->logger->warning('Quote id does not exist');
        }

        return $discountAmount;

    }
}
