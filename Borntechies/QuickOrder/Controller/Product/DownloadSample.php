<?php

namespace Borntechies\QuickOrder\Controller\Product;
 
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
 
class DownloadSample extends \Magento\Framework\App\Action\Action
{
    /**
     * @param Context $context
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Framework\Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }
    
    /**
     * Download Sample Csv Action
     *
     * @return object
     */
    public function execute()
    {
        $heading = [
            __('product_sku'),
            __('qty')
        ];
        $productCollection =
            [
                ["24-WB03",2],
                ["24-UG06",1],
                ["WS03",1],
                ["24-MB03",2],
                ["24-WG085_Group",1],
                ["24-MB05",1]
            ];
        $name = date('m_d_Y_H_i_s');
        $filepath = 'export/custom' . $name . '.csv';
        $this->directory->create('export');
        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();
        $stream->writeCsv($heading);

        foreach ($productCollection as $item) {
            $stream->writeCsv($item);
        }

        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = '1';

        $csvfilename = 'Product.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}
