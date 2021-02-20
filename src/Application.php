<?php

namespace App;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\HttpClient\HttpClient;

class Application
{
    /**
     * @var Terminal
     */
    private $terminal;

    public function __construct()
    {
        $this->terminal = new Terminal();
    }

    private function getRawRoutines(): string
    {
        /**
        $fileCached = __DIR__.'/../cache/routines.raw';
        if (file_exists($fileCached)) {
            return file_get_contents($fileCached);
        }
        */

        $client = HttpClient::create();
        $response = $client->request('GET', "http://localhost:1234/debug/pprof/goroutine?debug=2");

        if ($response->getStatusCode() != 200) {
            throw new \Exception('unable to query pprof');
        }

        $content = $response->getContent();
        /**
        file_put_contents($fileCached, $content);
        */
        return $content;
    }

    function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    private function getRoutines(string $rawRoutines) {
        $routines = [];

        $groupedRawRoutines = explode(PHP_EOL.PHP_EOL, $rawRoutines);
        foreach ($groupedRawRoutines as $groupedRawRoutine) {
            $rawRoutineLines = explode(PHP_EOL, $groupedRawRoutine);
            // goroutine 1 [chan send]:
            preg_match('/goroutine (?P<id>[0-9]+) \[(?P<status>[a-zA-Z ]+)\]:/i', $rawRoutineLines[0], $matches);
            preg_match("/\t(?P<path>[a-zA-Z0-9_\-\.\:\/]+)/i", $rawRoutineLines[2], $matchesFile);
            $function = (function($line) {
                $pos = strrpos($line, '/');
                return substr($line, $pos + 1);
            })($rawRoutineLines[1]);
            $code = (function($fileline){
                list($file, $line) = explode(':', $fileline);
                $lines = file($file);

                return $lines[(int) $line - 1];
            })($matchesFile['path']);
            $file = (function($path){
                $pos = strrpos($path, '/');
                return substr($path, $pos + 1);
            })($matchesFile['path']);
            $routines[] = [
                'id' => $matches['id'],
                'status' => $matches['status'],
                'file' => $file,
                'function' => $function,
                'code' => trim($code),
            ];
        }

        return $routines;
    }

    private function autoSize(Table  $table, $countColumns)
    {
        for ($i = 0; $i < $countColumns; $i++) {
            $table->setColumnWidth($i, ($this->terminal->getWidth() / $countColumns) - ($countColumns + 1));
        }
    }

    public function run()
    {
        $output = new ConsoleOutput();
        $sectionHelp = $output->section();
        $sectionPerformance = $output->section();
        $sectionRoutines = $output->section();

        $this->sectionHelp($sectionHelp);

        while(true) {
            $this->sectionPerformance($sectionPerformance);
            $this->sectionRoutines($sectionRoutines);
            sleep(1);
            $sectionRoutines->clear();
            $sectionPerformance->clear();
        }

    }

    private function sectionHelp(OutputInterface $output)
    {
        $helpTable = new Table($output);
        $this->autoSize($helpTable, 3);
        $helpTable->setHeaderTitle('How to use this tool ? ');
        $helpTable->setHeaders(
            ['Exit', 'Refesh', 'Show detail']
        );
        $helpTable->addRow(['enter "q"', 'enter "r"', 'enter "id"']);
        $helpTable->render();

        return $output;
    }

    private function sectionPerformance(OutputInterface $output)
    {
        static $lastMemory = 0;

        $symfony = new SymfonyStyle(new ArrayInput([]), $output);

        $memory = memory_get_usage();
        $diff = $memory - $lastMemory;
        $lastMemory = $memory;

        $symfony->title(sprintf('Memory : %s : %s : %s', $this->convert($memory), $memory, $diff > 0 ? '+'.$diff : $diff));

        return $output;
    }

    private function sectionRoutines(OutputInterface $output)
    {
        $routines = $this->getRoutines($this->getRawRoutines());
        $table = new Table($output);
        //$this->autoSize($table, 5);
        $table->setHeaderTitle('Goroutines');
        $table->setHeaders(['id', 'status', 'file', 'function', 'code']);
        foreach ($routines as $routine) {
            $table->addRow([$routine['id'], $routine['status'], $routine['file'], $routine['function'], $routine['code']]);
        }
        $table->setFooterTitle(date('h:i:s'));
        $table->render();

        return $output;
    }
}