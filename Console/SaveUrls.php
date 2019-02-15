<?php
/**
 * Created by PhpStorm.
 * User: matiasmatias
 * Date: 14/02/2019
 * Time: 23:31
 */

namespace PartyLite\UrlRewrite\Console;


use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SaveUrls
 * @package PartyLite\UrlRewrite\Console
 */
class SaveUrls extends Command
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $_categoryCollectionFactory;
    /**
     * @var \Magento\Catalog\Helper\Category
     */
    private $_categoryHelper;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $_productCollectionFactory;
    /**
     * @var \Magento\Catalog\Helper\Product
     */
    private $_productHelper;
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    private $productUrlRewriteGenerator;
    /**
     * @var \Magento\UrlRewrite\Model\UrlPersistInterface
     */
    private $urlPersist;

    /**
     *
     */
    const STORE = 'store';
    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    private $categoryUrlRewriteGenerator;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * SaveUrls constructor.
     * @param null $name
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory
     * @param \Magento\Catalog\Helper\Category $categoryHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Framework\App\State $state
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist
     * @param \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Framework\App\State $state,
        \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist,
        \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        $name = null
    )
    {

        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_categoryCollectionFactory = $collectionFactory;
        $this->_categoryHelper = $categoryHelper;
        $this->_productHelper = $productHelper;
        $this->state = $state;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        parent::__construct($name);
        $this->storeManager = $storeManager;
    }


    /**
     *
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::STORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Store'
            )
        ];
        $this->setName('partylite:url-rewrite');
        $this->setDescription('Iterates through all the active categories and saves them. Accepts --store={store_id} as an optional parameter');
        $this->setDefinition($options);

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        $store = $input->getOption(self::STORE) ? $input->getOption(self::STORE) : false;

        if ($store) {
            $output->writeln("Store: " . $store);

            $this->rewriteCategoryUrl($input, $output, $store);


            $this->rewriteProductUrl($output, $store);

        } else {
            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                $output->writeln('');
                $output->writeln("Store: " . $store->getCode());

                $this->rewriteCategoryUrl($input, $output, $store->getId());


                $this->rewriteProductUrl($output, $store->getId());
            }
        }









    }

    /**
     * @param bool $isActive
     * @param int $store
     * @param bool $level
     * @param bool $sortBy
     * @param bool $pageSize
     * @return mixed
     */
    private function getCategoryCollection($isActive = true, $store = 0, $level = false, $sortBy = false, $pageSize = false)
    {
        $collection = $this->_categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // select only active categories
        if ($isActive) {
            $collection->addIsActiveFilter();
        }

        // select categories of certain level
        if ($level) {
            $collection->addLevelFilter($level);
        }

        // sort categories by some value
        if ($sortBy) {
            $collection->addOrderField($sortBy);
        }

        // select certain number of categories
        if ($pageSize) {
            $collection->setPageSize($pageSize);
        }

        if ($store) {
            $collection->setStore($store);
        }

        return $collection;
    }

    /**
     * @param int $store
     * @return mixed
     */
    private function getProductsCollection($store = 0)
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addStoreFilter($store);
        return $collection->getItems();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $store
     */
    protected function rewriteCategoryUrl(InputInterface $input, OutputInterface $output, $store)
    {
        $category_collection = $this->getCategoryCollection(true, $store);

        $output->writeln('CATEGORIES: ');
        $output->writeln("");
        foreach ($category_collection as $category) {
            $output->writeln($category->getName());

            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $category->getId(),
                UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::REDIRECT_TYPE => 0,
                UrlRewrite::STORE_ID => $store,
            ]);

            try {
                $this->urlPersist->replace(
                    $this->categoryUrlRewriteGenerator->generate($category)
                );
            } catch (\Exception $e) {
                $output->writeln('Duplicated url for ' . $category->getId() . '');
            }

        }
    }

    /**
     * @param OutputInterface $output
     * @param $store
     */
    protected function rewriteProductUrl(OutputInterface $output, $store)
    {
        $output->writeln("");
        $output->writeln('PRODUCTS: ');
        $output->writeln("");


        $product_collection = $this->getProductsCollection($store);

        foreach ($product_collection as $product) {
            $output->writeln($product->getName());
            // we need to set store id

            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $product->getId(),
                UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::REDIRECT_TYPE => 0,
                UrlRewrite::STORE_ID => $store,
            ]);

            try {
                $this->urlPersist->replace(
                    $this->productUrlRewriteGenerator->generate($product)
                );
            } catch (\Exception $e) {
                $output->writeln('Duplicated url for ' . $product->getId() . '');
            }
        }
    }

}