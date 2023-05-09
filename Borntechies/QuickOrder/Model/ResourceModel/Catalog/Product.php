<?php
/**
 * Born Techies.
 *
 * @category  Borntechies
 * @package   Borntechies_QuickOrder
 * @author    Borntechies
 * @copyright Copyright (c) Born Techies Private Limited 
 * @license   https://borntechies.com/
 *
 */

namespace Borntechies\QuickOrder\Model\ResourceModel\Catalog;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Product extends \Magento\Catalog\Model\ResourceModel\Product
{
    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Magento\Eav\Model\Entity\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Factory $modelFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category $catalogCategory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $setFactory
     * @param \Magento\Eav\Model\Entity\TypeFactory $typeFactory
     * @param \Magento\Catalog\Model\Product\Attribute\DefaultAttributes $defaultAttributes
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Eav\Model\Entity\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Factory $modelFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category $catalogCategory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $setFactory,
        \Magento\Eav\Model\Entity\TypeFactory $typeFactory,
        \Magento\Catalog\Model\Product\Attribute\DefaultAttributes $defaultAttributes,
        $data = []
    ) {
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_collectionFactory = $collectionFactory;
        $this->_catalogCategory = $catalogCategory;
        $this->eventManager = $eventManager;
        $this->setFactory = $setFactory;
        $this->typeFactory = $typeFactory;
        $this->defaultAttributes = $defaultAttributes;
        parent::__construct(
            $context,
            $storeManager,
            $modelFactory,
            $categoryCollectionFactory,
            $catalogCategory,
            $eventManager,
            $setFactory,
            $typeFactory,
            $defaultAttributes
        );
    }

    /**
     * Retrieve product collection array
     *
     * @param string|array $searchKey
     * @return array
     */
    public function getCollection($searchKey)
    {
        $results = [];
        $collection = $this->_collectionFactory->create();
        if (is_array($searchKey)) {
            $filterArray = [];
            foreach ($searchKey as $value) {
                $filterArray[] =   ['attribute' => 'name', 'like' => '%'.$value.'%'];
                $filterArray[] =   ['attribute' => 'sku', 'like' => '%'.$value.'%'];
            }
            $collection->addFieldToSelect('has_options');
            $collection->addAttributeToFilter(
                $filterArray
            )
            ->addAttributeToFilter('visibility', ['in' => [4]]);
        } else {
            $collection->addFieldToSelect('has_options');
            $collection->addAttributeToFilter(
                [
                    ['attribute' => 'name', 'like' => '%'.$searchKey.'%'],
                    ['attribute' => 'sku', 'like' => '%'.$searchKey.'%']
                ]
            )->addAttributeToFilter('visibility', ['in' => [4]]);
        }

        $select = clone $collection->getSelect();
        $countQuery = $this->_getSelectCountSql($select);
        $count = $this->getConnection()->rawFetchRow($countQuery, 'cnt');
       
        if ($count > 0) {
            $website = $this->_storeManager->getWebsite()->getId();
            $collection->joinField(
                'is_in_stock',
                'cataloginventory_stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );

            $collection->addAttributeToSelect('name')
                ->addPriceData();
            $query = $collection->getSelect()->joinLeft(
                ['cpl' => $this->getTable('downloadable_link')],
                'e.entity_id = cpl.product_id'
            )->group('e.entity_id')
            ->limitPage(1, 20)
            ->__toString();

            $results = $this->getConnection()->fetchAll($query);
            
            return $results;
        }
        return $results;
    }

    /**
     * Get SQL for get record count
     *
     * @param object|null $select
     * @return \Magento\Framework\DB\Select
     */
    protected function _getSelectCountSql($select = null)
    {
        $countSelect = $this->_buildClearSelect($select);
        $countSelect->columns('COUNT(DISTINCT e.entity_id) as cnt');
        return $countSelect;
    }

    /**
     * Build clear select
     *
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     */
    protected function _buildClearSelect($select = null)
    {
        if (null === $select) {
            $select = clone $this->getSelect();
        }
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);

        return $select;
    }

    /**
     * Get Suggest Product By Id
     *
     * @param \Magento\Catalog\Model\Product $productModel
     * @return \Magento\Catalog\Model\Product
     */
    public function getSuggestProductById($productModel)
    {
        return $this->getEntityManager()->load($productModel, $productModel->getId());
    }

    /**
     * Get Entity Manager
     *
     * @return \Magento\Framework\EntityManager\EntityManager
     */
    private function getEntityManager()
    {
        if (null === $this->entityManager) {
            $this->entityManager = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\EntityManager\EntityManager::class);
        }
        return $this->entityManager;
    }
}
