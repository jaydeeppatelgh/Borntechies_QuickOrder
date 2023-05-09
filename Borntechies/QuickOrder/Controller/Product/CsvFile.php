<?php

namespace Borntechies\QuickOrder\Controller\Product;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class CsvFile extends \Magento\Framework\App\Action\Action
{
    /**
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\File\Csv $csvProcessor
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $uploader
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploader,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Filesystem\DirectoryList $directoryList
    ) {
        $this->csvProcessor = $csvProcessor;
        $this->uploader = $uploader;
        $this->filesystem = $filesystem;
        $this->productRepository = $productRepository;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }

    /**
     * Read and return CSV Data
     *
     * @return void
     */
    public function execute()
    {
        try {
            $files = $this->getRequest()->getFiles();
            $base_media_path = 'gallery/csv';
            $uploader = $this->uploader->create(
                ['fileId' => 'file']
            );
            $totalRowsCount = $this->getRequest()->getParam('totalRows');
              
            $uploader->setAllowedExtensions(['csv']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $mediaDirectory = $this->filesystem->getDirectoryRead(
                \Magento\Framework\App\Filesystem\DirectoryList::MEDIA
            );
            $result = $uploader->save(
                $mediaDirectory->getAbsolutePath($base_media_path)
            );
            $pubMediaPath = $this->directoryList->getPath('media');
            $csvFile = $pubMediaPath.'/'.$base_media_path.$result['file'];
            $importProductRawData = $this->csvProcessor->getData($csvFile);
            $response = [];
            $headers = $importProductRawData[0];
            $count = 0;
            $invalidsku = [];
            foreach ($importProductRawData as $data) {
                $newRow = [];
                $totalRowsCount++;
                if (++$count==1) {
                    continue;
                }
                foreach ($headers as $k => $key) {
                    if ($key == "product_sku") {
                        try {
                            $product = $this->getProductBySku($data[$k]);
                            $newRow['product_id'] = $product->getEntityId();
                            $newRow['name'] = $product->getName();
                            $newRow['has_options'] = $this->hasOptions($product);
                            $newRow['typeId'] = $product->getTypeId();
                            $newRow['price'] = $product->getPrice();
                            $newRow['is_saleable'] = $product->isSaleable();
                        } catch (\Exception $e) {
                            array_push($invalidsku, $data[$k]);
                            $responseData = [
                                'error' =>json_encode($invalidsku),
                                'errorcode' => $e->getCode(),
                            ];
                            $this->messageManager->addError(__("Invalid sku(s) are:".json_encode($invalidsku)));
                            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
                            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                            $resultJson->setData($responseData);
                            return $resultJson;
                        }
                        
                    }
                    
                    $newRow[$key] = $data[$k];
                    
                }
                if ($this->getRequest()->getParam('totalRows') > 0) {
                    $response[$totalRowsCount] = $newRow;
                } else {
                    $response[] = $newRow;
                }
                
            }
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData($response);
            return $resultJson;
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $responseData = [
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData($responseData);
            return $resultJson;
        }
    }

     /**
      * Product Object
      *
      * @param string $sku
      * @return object
      */
    public function getProductBySku($sku)
    {
        return $this->productRepository->get($sku);
    }

     /**
      * Check if product has Options
      *
      * @param object $product
      * @return int
      */
    protected function hasOptions($product)
    {
        $isOptions = 0;
        
        if ($product->getTypeId() == 'configurable' || $product->getHasOptions()) {
            $isOptions = 1;
        } elseif ($product->getTypeId() == 'downloadable') {
            $productData = $this->productRepository->getById($product->getEntityId());
            $count = count($productData->getDownloadableLinks());
            if ($count > 1) {
                $isOptions = 1;
            }
        } elseif ($product->getTypeId() == 'bundle') {
            $isOptions = 1;
        } elseif ($product->getTypeId() == 'grouped') {
            $isOptions = 1;
        }
        return $isOptions;
    }
}
