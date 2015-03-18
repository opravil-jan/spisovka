<?php

/**
 * Nette Framework Extras
 *
 * This source file is subject to the New BSD License.
 *
 * For more information please see http://extras.nettephp.com
 *
 * @copyright  Copyright (c) 2009 David Grudl
 * @license    New BSD License
 * @link       http://extras.nettephp.com
 * @package    Nette Extras
 * @version    $Id: VisualPaginator.php 4 2009-07-14 15:22:02Z david@grudl.com $
 */

/*use Nette\Application\Control;*/

/*use Nette\Paginator;*/



/**
 * Visual paginator control.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2009 David Grudl
 * @package    Nette Extras
 */
class VisualPaginator extends Nette\Application\UI\Control
{
    /** @var Nette\Utils\Paginator */
    private $paginator;

    /** @persistent */
    public $page = 1;



    /**
     * @return Nette\Paginator
     */
    public function getPaginator()
    {
        if (!$this->paginator) {
            $this->paginator = new Nette\Utils\Paginator;
        }
        return $this->paginator;
    }



    /**
     * Renders paginator.
     * @return void
     */
    public function render()
    {
        $paginator = $this->getPaginator();
        $page = $paginator->page;
        if ($paginator->pageCount < 2) {
            $steps = array($page);

        } else {
            $arr = range(max($paginator->firstPage, $page - 3), min($paginator->lastPage, $page + 3));
            $count = 4;
            $quotient = ($paginator->pageCount - 1) / $count;
            for ($i = 0; $i <= $count; $i++) {
                $arr[] = round($quotient * $i) + $paginator->firstPage;
            }
            sort($arr);
            $steps = array_values(array_unique($arr));
        }

        $request = Nette\Environment::getHttpRequest();
        $url = $request->getUrl()->getPath();
        $query_string = $request->getUrl()->getQuery();
        $query_params = "";
        parse_str($query_string, $query);
        //echo "<pre>"; print_r($query); echo "</pre>";
        unset($query['vp-page'],$query['seradit'],$query['filtr'],$query['hledat'],$query['do'], $query['presenter'], $query['action']);
        //echo "<pre>"; print_r($query); echo "</pre>";
        if ( count($query)>0 ) {
            foreach ( $query as $key=>$value ) {
                if ( empty($key) ) continue;
                $query_params .= "&". $key ."=". @urlencode($value);
            }
        }
        $query_params = substr($query_params, 1);

        if ( !empty($query_params) ) {
            $symbol = IS_SIMPLE_ROUTER ? '&' : '?';
            $this->template->query_first = "$symbol$query_params";
            $this->template->other_params = "&". $query_params;
        } else {
            $this->template->query_first = "";
            $this->template->other_params = "";
        }
                
        $this->template->steps = $steps;
        $this->template->paginator = $paginator;
        $this->template->onclick = '';
        if ($request->isAjax())
            $this->template->onclick = 'onclick="reloadDialog(this); return false;"';

        $this->template->setFile(dirname(__FILE__) . '/template.phtml');
        $this->template->render();
    }



    /**
     * Loads state informations.
     * @param  array
     * @return void
     */
    public function loadState(array $params)
    {
        parent::loadState($params);
        $this->getPaginator()->page = $this->page;
    }

}