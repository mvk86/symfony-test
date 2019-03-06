<?php

namespace App\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ParseCsvCommand extends Command
{
    protected static $defaultName = 'app:parse-csv';

    private $params;
    private $entityManager;
    private $filesToParse = [];
    private $fieldsMapping = [
        'Product Code' => 'strProductCode',
        'Product Name' => 'strProductName',
        'Product Description' => 'strProductDesc',
        'Stock' => 'intProductStock',
        'Cost in GBP' => 'decProductCost',
        'Discontinued' => 'dtmDiscontinued'
    ];

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $entityManager)
    {
        $this->params = $params;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('This script reads the CSV file, parse the contents and then insert the data into a MySQL database table')
            ->addArgument('test', InputArgument::OPTIONAL, 'This will perform everything the normal import does, but not insert the data into the database')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $csvFolder = $this->params->get('kernel.project_dir') . DIRECTORY_SEPARATOR . $this->params->get('app.csv_dir');

        // Looking for csv files in a folder
        $directoryData = array_diff(scandir($csvFolder), ['..', '.']);
        $this->filesToParse = [];
        foreach ($directoryData as $key => $value) {
            if (!is_dir($csvFolder . DIRECTORY_SEPARATOR . $value)) {
                $spl = new \SplFileInfo($csvFolder . DIRECTORY_SEPARATOR . $value);
                if ($spl->getExtension() == 'csv') {
                    $this->filesToParse[] = $csvFolder . DIRECTORY_SEPARATOR . $value;
                }
            }
        }

        if (!count($this->filesToParse)) {
            $io->note("There are no files to parse.\n Please, add CSV file(s) into the " . $csvFolder . " folder");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('auto_detect_line_endings', true);

        $io = new SymfonyStyle($input, $output);
        $testMode = $input->getArgument('test');

        foreach ($this->filesToParse as $file) {
            $headers = [];
            $rowsData = [];
            $incorrectData = [];
            $notImported = [];
            $numOfHeaderClmn = 0;

            $io->block('Processing file: ' . $file);

            $fileObj = new \SplFileObject($file);
            $fileObj->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
            while (!$fileObj->eof()) {
                $data = $fileObj->fgetcsv(',');
                if ($data[0] !== null) {
                    //Get header columns
                    if (empty($headers)) {
                        $headers = $data;
                        $numOfHeaderClmn = count($headers);
                    } else {
                        //Processing lines data

                        //Check for incorrect lines by number of columns
                        if (count($data) != $numOfHeaderClmn) {
                            $incorrectData[] = [
                                (int)$fileObj->key() + 1,
                                implode(', ', $data)
                            ];
                        }
                        else {
                            //Replace the file column header with Product entity fields
                            $line = array_combine($headers, $data);
                            foreach ($this->fieldsMapping as $search => $replace) {
                                if (isset($line[$search])) {
                                    $line[$replace] = $line[$search];
                                    unset($line[$search]);
                                }
                            }

                            //Rules processing
                            //stock item which costs less that £5 and has less than 10 stock
                            if (floatval($line['decProductCost']) < 5 && $line['intProductStock'] < 10) {
                                $line['reason'] = 'Stock item which costs less that £5 and has less than 10 stock';
                                $notImported[] = $line;
                            }
                            //stock items which cost over £1000
                            elseif (floatval($line['decProductCost']) > 1000) {
                                $line['reason'] = 'Stock item costs over £1000';
                                $notImported[] = $line;
                            }
                            else {
                                $rowsData[] = $line;
                            }
                        }
                    }
                }
            }

            $fileObj->seek(PHP_INT_MAX);

            unset($fileObj);

            //Check the data for unique product code
            $productCodes = array();
            $rowsData = array_filter($rowsData, function($el) use (&$productCodes, &$notImported) {
                if (in_array($el['strProductCode'], $productCodes)) { // if the id has already been seen
                    $el['reason'] = 'Such product code already in use';
                    $notImported[] = $el;
                    return false; // remove it
                } else {
                    $productCodes[] = $el['strProductCode']; // the id has now been seen
                    return true; // but keep the first occurrence of it
                }
            });

            //Insert data into DB
            if (!$testMode) {
                $batchSize = 20;
                foreach ($rowsData as $i => $item) {
                    $product = new Product();

                    // set entity data
                    foreach ($item as $field => $value) {
                        //Preprocessing fields values
                        switch ($field) {
                            case 'intProductStock':
                                $value = intval($value) ?? 0;
                                break;
                            case 'decProductCost':
                                $value = str_replace('$', '', $value);
                                $value = floatval($value);
                                break;
                            case 'dtmDiscontinued':
                                $value = $value == 'yes' ? new \DateTime('now') : null;
                                break;
                        }

                        $product->{'set' . ucfirst($field)}($value);
                    }

                    $this->entityManager->persist($product);
                    if (($i % $batchSize) === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear(); // Detaches all objects from Doctrine!
                    }
                }
                $this->entityManager->flush(); //Persist objects that did not make up an entire batch
                $this->entityManager->clear();
            }

            // Output process report
            if (count($rowsData)) {
                $tableResult = new Table($output);
                $tableResult->setHeaderTitle('Successfully imported lines');
                $tableResult
                    ->setHeaders($headers)
                    ->setRows($rowsData);
                $tableResult->render();

                $io->newLine();
            }

            if (count($incorrectData)) {
                $tableResult = new Table($output);
                $tableResult->setHeaderTitle('Incorrect lines');
                $tableResult
                    ->setHeaders(['Line #', 'Data'])
                    ->setRows($incorrectData);
                $tableResult->render();

                $io->newLine();
            }

            if (count($notImported)) {
                array_push($headers, 'Reason');
                $tableResult = new Table($output);
                $tableResult->setHeaderTitle('Not imported lines');
                $tableResult
                    ->setHeaders($headers)
                    ->setRows($notImported);
                $tableResult->render();

                $io->newLine();
            }
        }

        $io->success('Parsing process - DONE');
    }
}
