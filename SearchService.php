<?php

namespace AppBundle\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\DomCrawler\Crawler;

use AppBundle\Exception\SearchServiceException;

class SearchService
{
    protected $folder;
    protected $cacheFolder;
    protected $domain;
    protected $proto;
    protected $contentSelector;

    protected $wgetCmd = 'wget --directory-prefix="##FOLDER##" --recursive --level=10 --no-verbose --no-clobber --domains ##DOMAIN## --no-parent --force-directories --reject "*.js,*.css,*.jpg,*.JPG,*.png,*.PNG,*.jpeg,*.JPEG,*.gif,*.GIF,*.pdf,*.doc,*.docx,*.xls,*.xlsx,*.txt" ##PROTO##://##DOMAIN##';
    protected $searchCmd = 'grep -Rli ##SEARCH## ##FOLDER##/*';

    protected $buffer;

    public function __construct($kernelFolder, $domain, $proto='http', $contentSelector='')
    {
        $this->domain = preg_replace('#^(http|https)\:\/\/#', '', $domain);
        $this->proto = $proto;
        $this->cacheFolder = $kernelFolder.'/../src/AppBundle/Resources/searchCache';
        $this->folder = $this->cacheFolder.'/'.$this->domain;
        $this->contentSelector = $contentSelector;
    }

    public function rebuildIndex()
    {
        $this->clearIndex();
        $this->buildIndex();
    }

    // не очищает существующий индекс, сначала нужно вызвать clearIndex(), 
    // либо использовать rebuildIndex()
    public function buildIndex()
    {
        $this->download();
        $this->processDownloadedPages();
    }

    protected function download()
    {
        $cmd = $this->getWgetCommand();
        $process = new Process($cmd);
        $process->setTimeout(9999);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        $process->wait();
    }

    protected function processDownloadedPages($dir='')
    {
        if(empty($dir)){
            $dir = $this->folder;
        }

        if(!is_dir($dir)){
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            if(is_dir("$dir/$file")){
                $this->processDownloadedPages("$dir/$file");
            }else{
                $this->processDownloadedFile("$dir/$file");
            }
        }
    }

    protected function processDownloadedFile($path)
    {
        try{
            $crawler = new Crawler(file_get_contents($path));
            $title = $crawler->filter('head > title')->text();
            $content = $crawler->filter($this->contentSelector)->html();
            unset($crawler);

            $content = strip_tags($content);
            $content = preg_replace('/\s\s+/', ' ', $content);
            
            unlink($path);
            file_put_contents($path, $title."\n".$content);
        }catch(\Exception $e){
            unlink($path);
        }
    }


    public function getWgetCommand()
    {
        $cmd = str_replace('##DOMAIN##', $this->domain, $this->wgetCmd);
        $cmd = str_replace('##PROTO##', $this->proto, $cmd);
        $cmd = str_replace('##FOLDER##', $this->cacheFolder, $cmd);
        return $cmd;
    }

    public function clearIndex()
    {
        $this->delFolderTree($this->folder);
    }

    protected function delFolderTree($dir)
    {
        if(!is_dir($dir)){
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            if(is_dir("$dir/$file")){
                $this->delFolderTree("$dir/$file");
            }else{
                unlink("$dir/$file");
            }
        }

        return rmdir($dir);
    }

    public function search($search)
    {
        $search = trim($search, '\ \"\'');
        $output = $this->searchInFiles($search);
        return $this->prepareLinksFromSearshOutput($output);
    }

    protected function searchInFiles($search)
    {
        $cmd = $this->getSearchCommand($search);
        return $this->runCmdGetOutput($cmd);
    }

    public function getSearchCommand($search)
    {
        setlocale(LC_CTYPE, "en_US.UTF-8");
        $cmd = str_replace('##FOLDER##', $this->folder, $this->searchCmd);
        $cmd = str_replace('##SEARCH##', escapeshellarg($search), $cmd);
        return $cmd;
    }

    protected function runCmdGetOutput($cmd)
    {
        $cmd = 'export LANG=en_US.UTF-8; '.$cmd;
        $process = new Process($cmd);
        $process->run();
        return $process->getOutput();
    }

    protected function prepareLinksFromSearshOutput($output)
    {
        $data = array();
        foreach (explode("\n", $output) as $filename) {
            $entry = array();
            $link = str_replace($this->folder, $this->proto.'://'.$this->domain, $filename);
            $link = str_replace('index.html', '', $link);
            if(!empty($link)){
                try{
                    $entry['link'] = $link;
                    $entry['title'] = file($filename)[0];
                    $data[] = $entry;
                }catch(\Exception $e){
                    // dump($e->getMessage());
                }
            }
        }
        return $data;
    }

    protected function findTitleOfHtmlPage($html)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filter('head > title')->text();
        unset($crawler);
        return $title;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function getProto()
    {
        return $this->proto;
    }

    public function getHost()
    {
        return $this->proto.'://'.$this->domain;
    }

}