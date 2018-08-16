<?php

/**
 * Class \Klevu\Search\Model\Observer
 *
 * @method UpdateCategoryPageLayout($flag)
 * 
 */
namespace Klevu\Categorynavigation\Model\Observer;
 
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\LayoutInterface;
use \Klevu\Categorynavigation\Helper\Data;

class UpdateCategoryPageLayout implements ObserverInterface
{
    const KLEVU_PRESERVE_LAYOUT    = 2;
    const MAGENTO_DEFAULT     = 1;
    const KLEVU_TEMPLATE_LAYOUT = 3;
    
    public function __construct(
        \Klevu\Categorynavigation\Helper\Data $searchHelper
    ) {
        $this->_searchHelper = $searchHelper;
    }
    /**
     * Add handles to the page.
     *
     * @param Observer $observer
     * @event layout_load_before
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var LayoutInterface $layout */
        $action = $observer->getData('full_action_name');
        $layout = $observer->getData('layout');
        $helper = $this->_searchHelper;
        if($action == "catalog_category_view" && $helper->categoryLandingStatus() == static::KLEVU_TEMPLATE_LAYOUT)
        {
        	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$category = $objectManager->get('Magento\Framework\Registry')->registry('current_category');//get current category
			$categoryDisplayMode = $category->getData('display_mode');
	        if($categoryDisplayMode != "PAGE" )
	        {
				//$layout->unsetElement('category.image');
		        //$layout->unsetElement('category.description');
		        //$layout->unsetElement('category.cms');
		        $layout->unsetElement('category.products');
		        $layout->unsetElement('category.products.list');
		        $layout->unsetElement('category.product.type.details.renderers');
		        $layout->unsetElement('category.product.addto');
		        $layout->unsetElement('category.product.addto.compare');
		        $layout->unsetElement('product_list_toolbar');
		        $layout->unsetElement('product_list_toolbar_pager');
		        $layout->unsetElement('category.product.addto.wishlist');
		    	$layout->unsetElement('catalog.leftnav');
		    	$layout->unsetElement('catalog.navigation.state');
		        $layout->unsetElement('catalog.navigation.renderer');
		        $layout->getUpdate()->addHandle('klevu_category_index');
			}   	
		}     
    }
}