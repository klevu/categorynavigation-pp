<?php

namespace Klevu\Categorynavigation\Plugin\Framework\Search\Request;

use Klevu\Categorynavigation\Helper\Data as KlevuHelperDataCatNav;
use Klevu\Categorynavigation\Model\Api\Magento\Request\CategoryInterface as KlevuCategoryApi;
use Klevu\Search\Helper\Config as KlevuHelperConfig;
use Klevu\Search\Helper\Data as KlevuHelperData;
use Magento\Catalog\Model\SessionFactory;
use Magento\Framework\Registry as MagentoRegistry;
use Magento\Framework\Search\Request\Cleaner as MagentoCleaner;
use Magento\Framework\Session\SessionManagerInterface as MageSessionManager;
use Magento\PageCache\Model\Config as MagentoPageCache;

/**
 * Class CleanerPluginForCatNav
 * @package Klevu\Categorynavigation\Plugin\Framework\Search\Request
 */
class CleanerPluginForCatNav
{
    const KLEVU_PRESERVE_LAYOUT = 2;
    const MAGENTO_DEFAULT = 1;
    const KLEVU_TEMPLATE_LAYOUT = 3;

    /**
     * @var \Magento\Catalog\Model\Session|MageSessionManager
     */
    protected $sessionObjectHandler;

    /**
     * @var MagentoRegistry
     */
    protected $magentoRegistry;

    /**
     * @var MagentoCleaner
     */
    protected $magentoCleaner;

    /**
     * @var KlevuCategoryApi
     */
    protected $klevuCategoryRequest;

    /**
     * @var KlevuHelperData
     */
    protected $klevuHelperData;

    /**
     * @var KlevuHelperConfig
     */
    protected $klevuHelperConfig;

    /**
     * @var KlevuHelperDataCatNav
     */
    protected $klevuHelperDataCatNav;

    public function __construct(
        MagentoRegistry $mageRegistry,
        MagentoCleaner $mageCleaner,
        MagentoPageCache $magePageCache,
        MageSessionManager $sessionManager,
        SessionFactory $sessionFactory,
        KlevuCategoryApi $klevuCategoryRequest,
        KlevuHelperData $klevuHelperData,
        KlevuHelperConfig $klevuHelperConfig,
        KlevuHelperDataCatNav $klevuHelperDataCatNav
    )
    {
        $this->magentoRegistry = $mageRegistry;
        $this->magentoCleaner = $mageCleaner;
        $this->sessionFactory = $sessionFactory;
        $this->klevuCategoryRequest = $klevuCategoryRequest;
        $this->klevuHelperData = $klevuHelperData;
        $this->klevuHelperConfig = $klevuHelperConfig;
        $this->klevuHelperDataCatNav = $klevuHelperDataCatNav;

        if ($magePageCache->isEnabled()) {
            $this->sessionObjectHandler = $this->sessionFactory->create();
        } else {
            $this->sessionObjectHandler = $sessionManager;
        }
    }

    /**
     *
     * @param $subject
     * @param $result
     * @param $requestData
     */
    public function afterClean(MagentoCleaner $subject, $result)
    {
        try {
            //Check if query is for catalog_view_container ( category view page )
            if (!isset($result['queries']['catalog_view_container'])) {
            	return $result;
            }

            //Return if PRESERVE Layout is not enabled or module is not configured
            if (!$this->klevuHelperConfig->isExtensionConfigured() || $this->klevuHelperDataCatNav->categoryLandingStatus() != static::KLEVU_PRESERVE_LAYOUT) {
            	return $result;
            }
            $klevuRequestData = $this->klevuQueryCleanupCategory($result);
            return $klevuRequestData;
        } catch (\Exception $e) {
            $this->klevuHelperData->log(\Zend\Log\Logger::CRIT, sprintf("Klevu_CatNav_Cleaner::Cleaner ERROR occured :%s", $e->getMessage()));
            return $result;
        }
        return $result;
    }

    /**
     * Klevu Cleanup for Category Navigation
     *
     * @param $requestData
     * @return mixed
     */
    public function klevuQueryCleanupCategory($requestData)
    {
        $catValue = $requestData['filters']['category_filter']['value'];
        $queryScope = $requestData['dimensions']['scope']['value'];

        $idList = $this->sessionObjectHandler->getData('ids_' . $queryScope . '_cat_' . $catValue);
        if (!$idList) {
            $idList = $this->klevuCategoryRequest->_getKlevuProductIds();
            if (empty($idList)) $idList = array();
            $this->sessionObjectHandler->setData('ids_' . $queryScope . '_cat_' . $catValue, $idList);
        }

        //register the id list so it will be used when ordering
        $this->magentoRegistry->unregister('search_ids');
        $this->magentoRegistry->register('search_ids', $idList);

        //To get the Variant Selection for Category Pages if Preserve Layout Option
        $parentChildIDs = $this->sessionObjectHandler->getData('parentChildIDs_' . $queryScope . '_cat_' . $catValue);
        if (!$parentChildIDs) {
            $parentChildIDs = $this->klevuCategoryRequest->getKlevuVariantParentChildIds();
            if (empty($parentChildIDs)) $parentChildIDs = array();
            $this->sessionObjectHandler->setData('parentChildIDs_' . $queryScope . '_cat_' . $catValue, $parentChildIDs);
        }
        //register the parentChildIDs
        $this->magentoRegistry->unregister('parentChildIDsCatNav');
        $this->magentoRegistry->register('parentChildIDsCatNav', $parentChildIDs);

        $currentEngine = $this->getCurrentSearchEngine();
        if ($currentEngine !== "mysql") {
            if (isset($requestData['sort'])) {
                if (count($requestData['sort']) > 0) {
                    foreach ($requestData['sort'] as $key => $value) {
                        if ($value['field'] == "personalized") {
                            $this->magentoRegistry->unregister('current_order');
                            $this->magentoRegistry->register('current_order', "personalized");
                        }

                    }
                }
            }

            $current_order = $this->magentoRegistry->registry('current_order');
            if (!empty($current_order)) {
                if ($current_order == "personalized") {
                    $this->magentoRegistry->unregister('from');
                    $this->magentoRegistry->unregister('size');
                    $this->magentoRegistry->register('from', $requestData['from']);
                    $this->magentoRegistry->register('size', $requestData['size']);
                    $requestData['from'] = 0;
                    $requestData['size'] = count($idList);
                    $requestData['sort'] = array();
                }
            }
        }
        return $requestData;
    }

    /**
     * Return current catalog search engine
     *
     * @return mixed
     */
    private function getCurrentSearchEngine()
    {
        return $this->klevuHelperConfig->getCurrentEngine();
    }


}
