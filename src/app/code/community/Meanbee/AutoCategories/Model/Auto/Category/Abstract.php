<?php

abstract class Meanbee_AutoCategories_Model_Auto_Category_Abstract extends Mage_Core_Model_Abstract {

    /** @var Mage_Catalog_Model_Category $category */
    protected $category;

    /**
     * Check if the auto category is enabled.
     *
     * @return boolean
     */
    abstract public function isEnabled();

    /**
     * Return the id of the Category model used for this auto category.
     *
     * @return int
     */
    abstract protected function getCategoryId();

    /**
     * Apply a filter to the given product collection to only select the
     * products which should be in this auto category.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection $collection
     *
     * @return $this
     */
    abstract protected function applyFilter(Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection $collection);

    /**
     * Return the Category model used for this auto category.
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory() {
        if (!$this->category) {
            $this->category = Mage::getModel('catalog/category')->load($this->getCategoryId());
        }

        return $this->category;
    }

    /**
     * Process the given product ids against the category, adding them if they should be in it
     * or removing them if they no longer match the filter. If no products are specified, process
     * all products.
     *
     * @param array $products
     */
    public function maintain($products = array()) {
        if (!$this->isEnabled()) {
            return;
        }

        $collection = Mage::getModel('catalog/product')->getCollection();

        $this->applyFilter($collection);

        // Remove products not matching the filter anymore
        $select = clone $collection->getSelect();
        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array('product_id' => 'entity_id'));
        $where = array(
            'category_id = ?' => $this->getCategoryId(),
            'product_id NOT IN (?)' => $select
        );
        if (!empty($products)) {
            $where['product_id IN (?)'] = $products;
        }

        $this->getConnection()->delete($this->getCategoryProductTable(), $where);

        // Add products if they match the filter
        if (!empty($products)) {
            $collection->addIdFilter($products);
        }
        $select = clone $collection->getSelect();
        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'category_id' => new Zend_Db_Expr($this->getCategoryId()),
                'product_id'  => 'entity_id',
                'position'    => $this->getPositionExpr()
            ));
        $insert = $select->insertIgnoreFromSelect($this->getCategoryProductTable(), array('category_id', 'product_id', 'position'));

        $this->getConnection()->query($insert);
    }

    /**
     * Return the database expression for the product position in the category.
     *
     * @return Zend_Db_Expr
     */
    protected function getPositionExpr() {
        return new Zend_Db_Expr(0);
    }

    /**
     * Get a database connection (with write permissions).
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected function getConnection() {
        return Mage::getModel('core/resource')->getConnection('core_write');
    }

    /**
     * Return the name of the table used to store products in categories.
     *
     * @return string
     */
    protected function getCategoryProductTable() {
        return Mage::getModel('core/resource')->getTableName('catalog/category_product');
    }
}