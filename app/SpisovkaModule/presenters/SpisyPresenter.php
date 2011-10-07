<?php

class Spisovka_SpisyPresenter extends BasePresenter
{

    private $typ_evidence = null;
    private $oddelovac_poradi = null;
    private $spis_plan;
    private $hledat;

    public function startup()
    {
        $user_config = Environment::getVariable('user_config');
        $this->typ_evidence = 0;
        if ( isset($user_config->cislo_jednaci->typ_evidence) ) {
            $this->typ_evidence = $user_config->cislo_jednaci->typ_evidence;
        } else {
            $this->typ_evidence = 'priorace';
        }
        $this->template->Typ_evidence = $this->typ_evidence;
        
        if ( isset($user_config->cislo_jednaci->oddelovac) ) {
            $this->oddelovac_poradi = $user_config->cislo_jednaci->oddelovac;
        } else {
            $this->oddelovac_poradi = '/';
        }
        $this->template->Oddelovac_poradi = $this->oddelovac_poradi;
        parent::startup();
    }

    public function actionVyber()
    {
        $Spisy = new Spis();
        $this->spis_plan = $Spisy->seznamSpisovychPlanu();
        
        $this->template->dokument_id = $this->getParam('id',$this->getParam('dokument_id',null));
        if ( empty($this->template->dokument_id) ) {
            if ( isset($_POST['dokument_id']) ) {
                $this->template->dokument_id = $_POST['dokument_id'];
            }
        }
    }
    
    
    public function renderVyber()
    {

        $Spisy = new Spis();
        $session_spisplan = Environment::getSession('s3_spisplan');
        $spis_id = $this->getParam('spisplan_id',null);

        if ( !is_null($spis_id) ) {
            // spis_id
        } else if ( !empty($session_spisplan->spis_id) ) {
            $spis_id = $session_spisplan->spis_id;
        } else if ( count($this->spis_plan)>0 ) {
            reset($this->spis_plan);
            $spis_id = key($this->spis_plan);
        } else {
            $spis_id = null;
        }        
        
        if ( !empty($spis_id) ) {

            $this->template->SpisovyPlan = $Spisy->getInfo($spis_id);

            $args = null;
            if ( !empty($hledat) ) {
                $args = array( 'where'=>array(array("tb.nazev LIKE %s",'%'.$hledat.'%')));
            }

            /*$user_config = Environment::getVariable('user_config');
            $vp = new VisualPaginator($this, 'vp');
            $paginator = $vp->getPaginator();
            $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;*/

            $args = $Spisy->spisovka($args);
            /*$result = $Spisy->seznam($args, 5, $spis_id);
            $paginator->itemCount = count($result);
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $this->template->seznam = $seznam;*/

            $result = $Spisy->seznam($args, 5, $spis_id);
            $this->template->seznam = $result->fetchAll();

            $session_spisplan->spis_id = $spis_id;
        } else {
            $this->template->seznam = null;
        }

        $this->template->spisplanForm = $this['spisplanForm'];        

    }

    public function renderVybrano()
    {

        $spis_id = $this->getParam('id',null);
        $dokument_id = $this->getParam('dok_id',null);
        $Spisy = new Spis();

        $spis = $Spisy->getInfo($spis_id);
        if ( $spis ) {

            // Propojit s dokumentem
            $DokumentSpis = new DokumentSpis();
            $DokumentSpis->pripojit($dokument_id, $spis_id);

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::SPIS_DOK_PRIPOJEN,'Dokument přidán do spisu "'. $spis->nazev .'"');

            echo '###vybrano###'. $spis->nazev;
            $this->terminate();

        } else {
            // chyba
            

            $Spisy = new Spis();
            $args = null;// array( 'where'=>array("nazev_subjektu like %s",'%blue%') );
            $seznam = $Spisy->seznam($args);
            $this->template->seznam = $seznam;
            $this->template->dokument_id = $dokument_id;

            $this->template->chyba = 1;
            
            $this->template->render('vyber');
        }
        
    }


    public function actionDefault()
    {
        $Spisy = new Spis();
        $this->spis_plan = $Spisy->seznamSpisovychPlanu();
    }

    public function renderDefault($hledat = null)
    {

        $post = $this->getRequest()->getPost();
        if ( isset($post['hromadna_submit']) ) {
            $this->actionAkce($post);
        }

        $Spisy = new Spis();
        $session_spisplan = Environment::getSession('s3_spisplan');
        $spis_id = $this->getParam('id',null);

        if ( !is_null($spis_id) ) {
            // spis_id
        } else if ( !empty($session_spisplan->spis_id) ) {
            $spis_id = $session_spisplan->spis_id;
        } else if ( count($this->spis_plan)>0 ) {
            reset($this->spis_plan);
            $spis_id = key($this->spis_plan);
        } else {
            $spis_id = null;
        }

        if ( !empty($spis_id) ) {

            $this->template->SpisovyPlan = $Spisy->getInfo($spis_id);

            $args = null;
            if ( !empty($hledat) ) {
                $args = array( 'where'=>array(array("tb.nazev LIKE %s",'%'.$hledat.'%')));
            }

            $user_config = Environment::getVariable('user_config');
            $vp = new VisualPaginator($this, 'vp');
            $paginator = $vp->getPaginator();
            $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;

            $args = $Spisy->spisovka($args);
            $result = $Spisy->seznam($args, 5, $spis_id);
            $paginator->itemCount = count($result);
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $this->template->seznam = $seznam;


            //$seznam = $Spisy->seznam(null, 0, $spis_id);
            //$this->template->seznam = $seznam;

            $session_spisplan->spis_id = $spis_id;
        } else {
            $this->template->seznam = null;
        }

        $this->template->spisplanForm = $this['spisplanForm'];

        /*
        $args = null;
        if ( !empty($hledat) ) {
            $args = array( 'where'=>array(array("tb.nazev LIKE %s",'%'.$hledat.'%')));
        }

        $Spisy = new Spis();

        $user_config = Environment::getVariable('user_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;

        $result = $Spisy->seznam($args,5);
        $paginator->itemCount = count($result);
        $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        $this->template->seznam = $seznam;*/

    }

    public function actionDetail()
    {
        
        $spis_id = $this->getParam('id',null);
        // Info o spisu
        $Spisy = new Spis();
        $this->template->Spis = $spis = $Spisy->getInfo($spis_id);
        
        $SpisovyZnak = new SpisovyZnak();
        $spisove_znaky = $SpisovyZnak->select(11);
        $this->template->SpisoveZnaky = $spisove_znaky;
        
        if ( isset($spisove_znaky[ $spis->spisovy_znak_id ]) ) {
            $this->template->SpisZnak_popis = $spisove_znaky[ $spis->spisovy_znak_id ]->popis;
            $this->template->SpisZnak_nazev = $spisove_znaky[ $spis->spisovy_znak_id ]->nazev;
        } else {
            $this->template->SpisZnak_popis = "";
            $this->template->SpisZnak_nazev = "";
        }        

        $DokumentSpis = new DokumentSpis();
        //$user_config = Environment::getVariable('user_config');
        //$vp = new VisualPaginator($this, 'vp');
        //$paginator = $vp->getPaginator();
        //$paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;
        $result = $DokumentSpis->dokumenty($spis_id,1);
        //$paginator->itemCount = count($result);
        //$seznam = $DokumentSpis->fetchPart($result, $paginator->offset, $paginator->itemsPerPage);
        //$this->template->seznam = $seznam;
        $this->template->seznam = $result;

        $this->template->FormUpravit = $this->getParam('upravit',null);

    }

    public function renderDetail()
    {
        $this->template->upravitForm = $this['upravitForm'];
    }

    public function renderNovy()
    {
        $SpisovyZnak = new SpisovyZnak();
        $spisove_znaky = $SpisovyZnak->seznam(null);
        $this->template->SpisoveZnaky = $spisove_znaky;
        $this->template->spisForm = $this['novyForm'];
    }    
    
    public function actionAkce($data)
    {

        //echo "<pre>"; print_r($data); echo "</pre>"; exit;

        if ( isset($data['hromadna_akce']) ) {
            $Spis = new Spis();
            $user = Environment::getUser()->getIdentity();
            switch ($data['hromadna_akce']) {
                /* Predani vybranych spisu do spisovny  */
                case 'predat_spisovna':
                    if ( isset($data['spis_vyber']) ) {
                        $count_ok = $count_failed = 0;
                        foreach ( $data['spis_vyber'] as $spis_id ) {
                            $stav = $Spis->predatDoSpisovny($spis_id);
                            if ( $stav === true ) {
                                $count_ok++;
                            } else {
                                if ( is_string($stav) ) {
                                    $this->flashMessage($stav,'warning');
                                }
                                $count_failed++;
                            }
                        }
                        if ( $count_ok > 0 ) {
                            $this->flashMessage('Úspěšně jste předal '.$count_ok.' spisů do spisovny.');
                        }
                        if ( $count_failed > 0 ) {
                            $this->flashMessage($count_failed.' spisů se nepodařilo předat do spisovny!','warning');
                        }
                        if ( $count_ok > 0 && $count_failed > 0 ) {
                            $this->redirect('this');
                        }
                    }
                    break;
                default:
                    break;
            }


        }

    }

    public function actionStav()
    {

        $spis_id = $this->getParam('id');
        $stav = $this->getParam('stav');

        $Spis = new Spis();

        switch ($stav) {
            case 'uzavrit':
                $stav = $Spis->zmenitStav($spis_id, 0);
                if ( $stav === -1 ) {
                    $this->flashMessage('Spis nelze uzavřít. Jeden nebo více dokumentů nejsou vyřízeny.','warning');
                } else if ( $stav ) {
                    $this->flashMessage('Spis byl uzavřen.');
                } else {
                    $this->flashMessage('Spis se nepodařilo uzavřit.','error');
                }
                break;
            case 'otevrit':
                if ( $Spis->zmenitStav($spis_id, 1) ) {
                    $this->flashMessage('Spis byl otevřen.');
                } else {
                    $this->flashMessage('Spis se nepodařilo otevřít.','error');
                }
                break;
            default:
                break;
        }

        $this->redirect(':Spisovka:Spisy:detail',array('id'=>$spis_id));

    }

    protected function createComponentUpravitForm()
    {

        $Spisy = new Spis();

        $spis = @$this->template->Spis;
        $typ_spisu = Spis::typSpisu();
        $stav_select = Spis::stav();
        $spousteci = SpisovyZnak::spousteci_udalost(null,1);
        $skar_znak = array('A'=>'A','S'=>'S','V'=>'V');

        $SpisovyZnak = new SpisovyZnak();
        $spisznak_seznam = $SpisovyZnak->select(2);

        $spousteci_udalost = $SpisovyZnak->spousteci_udalost(null,1);

        //$spisy = $Spisy->select(1,@$spis->id);
        //$params = array('where'=> array("tb.typ = 'VS'") );
        //$spisy = $Spisy->select(11, null, $session_spisplan->spis_id, $params);

        $form1 = new AppForm();
        $form1->addHidden('id')
                ->setValue(@$spis->id);
        $form1->addSelect('typ', 'Typ spisu:', $typ_spisu)
                ->setValue(@$spis->typ);
        $form1->addText('nazev', 'Název spisu:', 50, 80)
                ->setValue(@$spis->nazev)
                ->addRule(Form::FILLED, 'Název spisu musí být vyplněn!');
        $form1->addText('popis', 'Popis:', 50, 200)
                ->setValue(@$spis->popis);
        //$form1->addSelect('parent_id', 'Připojit k:', $spisy)
        //        ->setValue(@$spis->parent_id);
        //$form1->addSelect('stav', 'Změnit stav na:', $stav_select)
        //             ->setValue(@$spis->stav);

        $form1->addSelect('spisovy_znak_id', 'Spisový znak:', $spisznak_seznam)
                ->setValue(@$spis->spisovy_znak_id)
                ->controlPrototype->onchange("vybratSpisovyZnak();");
        
        //$form1->addText('spisovy_znak', 'Spisový znak:', 10, 10)
        //        ->setValue($spisovy_znak_max)
        //        ->getControlPrototype()->onblur("return kontrolaSpisovyZnak('upravit');");

        $form1->addSelect('skartacni_znak', 'Skartační znak:', $skar_znak)
                ->setValue(@$spis->skartacni_znak)
                ->controlPrototype->disabled = TRUE;
        $form1->addText('skartacni_lhuta','Skartační lhuta: ', 5, 5)
                ->setValue(@$spis->skartacni_lhuta)
                ->controlPrototype->readonly = TRUE;
        $form1->addSelect('spousteci_udalost_id', 'Spouštěcí událost:', $spousteci)
                ->setValue(@$spis->spousteci_udalost_id)
                ->controlPrototype->readonly = TRUE;

        $unixtime = strtotime(@$spis->datum_otevreni);
        if ( $unixtime == 0 ) {
            $form1->addDatePicker('datum_otevreni', 'Datum otevření:', 10);
        } else {
            $form1->addDatePicker('datum_otevreni', 'Datum otevření:', 10)
                ->setValue( date('d.m.Y',$unixtime) );
        }

        $unixtime = strtotime(@$spis->datum_uzavreni);
        if ( $unixtime == 0 ) {
            $form1->addDatePicker('datum_uzavreni', 'Datum uzavření:', 10);
        } else {
            $form1->addDatePicker('datum_uzavreni', 'Datum uzavření:', 10)
                ->setValue( date('d.m.Y',$unixtime) );
        }        
        
        $form1->addSubmit('upravit', 'Upravit')
                 ->onClick[] = array($this, 'upravitClicked');
        $form1->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }


    public function upravitClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $spis_id = $data['id'];
        unset($data['id']);

        $Spisy = new Spis();

        try {
            $res = $Spisy->upravit($data, $spis_id);
            if ( is_object($res) ) {
                $this->flashMessage('Spis "'. $data['nazev'] .'" se nepodařilo upravit.','warning');
                $this->flashMessage($res->getMessage(),'warning');
                $this->redirect(':Spisovka:Spisy:detail',array('id'=>$spis_id));
            } else {
                $this->flashMessage('Spis  "'. $data['nazev'] .'"  byl upraven.');
                $this->redirect(':Spisovka:Spisy:detail',array('id'=>$spis_id));
            }
        } catch (DibiException $e) {
            $this->flashMessage('Spis "'. $data['nazev'] .'" se nepodařilo upravit.','warning');
            $this->flashMessage($e->getMessage(),'warning');
        }

    }

    public function stornoClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $spis_id = $data['id'];
        if ( $spis_id ) {
            $this->redirect(':Spisovka:Spisy:detail',array('id'=>$spis_id));
        } else {
            $this->redirect(':Spisovka:Spisy:default',array('id'=>$spis_id));
        }
        
    }

    protected function createComponentNovyForm()
    {

        $Spisy = new Spis();

        //$typ_spisu = Spis::typSpisu();
        $stav_select = Spis::stav();
        $spousteci = SpisovyZnak::spousteci_udalost(null,1);
        $skar_znak = array('A'=>'A','S'=>'S','V'=>'V');

        $session_spisplan = Environment::getSession('s3_spisplan');

        $params = array('where'=> array("tb.typ = 'VS'") );
        $spisy = $Spisy->select(11, null, $session_spisplan->spis_id, $params);

        $SpisovyZnak = new SpisovyZnak();
        $spisznak_seznam = $SpisovyZnak->select(2);
        //$spisovy_znak_max = $Spisy->maxSpisovyZnak( $session_spisplan->spis_id );

        $form1 = new AppForm();
        $form1->addHidden('typ')
                ->setValue('S');
        $form1->addText('nazev', 'Název spisu:', 50, 80)
                ->addRule(Form::FILLED, 'Název spisu musí být vyplněn!');
        $form1->addText('popis', 'Popis:', 50, 200);
        $form1->addSelect('parent_id', 'Složka:', $spisy)
                ->getControlPrototype()->onchange("return zmenitSpisovyZnak('novy');");

        //$form1->addText('spisovy_znak', 'Spisový znak:', 10, 10)
        //        ->setValue($spisovy_znak_max)
        //        ->getControlPrototype()->onblur("return kontrolaSpisovyZnak('novy');");
        $form1->addSelect('spisovy_znak_id', 'Spisový znak:', $spisznak_seznam)
                ->controlPrototype->onchange("vybratSpisovyZnak();");
        $form1->addSelect('skartacni_znak', 'Skartační znak:', $skar_znak);
        $form1->addText('skartacni_lhuta','Skartační lhuta: ', 5, 5);
        $form1->addSelect('spousteci_udalost_id', 'Spouštěcí událost:', $spousteci);
        $form1->addDatePicker('datum_otevreni', 'Datum otevření:', 10)
                ->setValue( date('d.m.Y') );
        $form1->addDatePicker('datum_uzavreni', 'Datum uzavření:', 10);

        $form1->addSubmit('vytvorit', 'Vytvořit')
                 ->onClick[] = array($this, 'vytvoritClicked');
        $form1->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function vytvoritClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        //echo "<pre>"; print_r($data); echo "</pre>"; exit;
        
        $Spisy = new Spis();

        $dokument_id = @$data['dokument_id'];
        $this->template->dokument_id = $dokument_id;
        unset($data['dokument_id']);
        
        try {
            $spis_id = $Spisy->vytvorit($data);
            if ( is_object($spis_id) ) {
                if ( $dokument_id ) {
                    echo '<div class="flash_message flash_error">Spis "'. $data['nazev'] .'" se nepodařilo vytvořit.</div>';
                    echo '<div class="flash_message flash_error">Error: '. $spis_id->getMessage() .'</div>';
                } else {
                    $this->flashMessage('Spis "'. $data['nazev'] .'" se nepodařilo vytvořit.','error');
                    $this->flashMessage('Error: '. $spis_id->getMessage(),'error');
                }
            } else {
                if ( $dokument_id ) {
                    echo '<div class="flash_message flash_info">Spis "'. $data['nazev'] .'"  byl vytvořen.</div>';                
                } else {
                    $this->flashMessage('Spis "'. $data['nazev'] .'"  byl vytvořen.');
                }
                if (!$this->isAjax()) {
                    $this->redirect(':Spisovka:Spisy:detail',array('id'=>$spis_id));
                    //$this->redirect('this');
                } else {
                    $this->invalidateControl('dokspis');
                }                
            }
        } catch (DibiException $e) {
            if ( $dokument_id ) {
                echo '<div class="flash_message flash_error">Spis "'. $data['nazev'] .'" se nepodařilo vytvořit.</div>';
            } else {
                $this->flashMessage('Spis "'. $data['nazev'] .'" se nepodařilo vytvořit.','warning');
            }
            
            
        }
    }

    /**
     * Select formular se seznamem spisovych planu
     *
     * @return AppForm
     */
    protected function createComponentSpisplanForm()
    {

        $session_spisplan = Environment::getSession('s3_spisplan');

        //Debug::dump($session_spisplan->spis_id);

        $form = new AppForm();
        
        if ( $this->getAction() == "vyber" ) {
            $form->getElementPrototype()->onsubmit('return spisplanZmenit(this);');
        }
        
        if ( $this->getAction() == "vyber" ) {
            
            $form->addHidden('dokument_id')
                    ->setValue(@$this->template->dokument_id);
            
            $form->addSelect('spisplan', 'Zobrazit spisový plán:', $this->spis_plan)
                    ->setValue($session_spisplan->spis_id)
                    ->getControlPrototype()->onchange("return spisplanZmenit(document.forms['frm-spisplanForm']);");
        } else {
            $form->addSelect('spisplan', 'Zobrazit spisový plán:', $this->spis_plan)
                    ->setValue($session_spisplan->spis_id)
                    ->getControlPrototype()->onchange("return document.forms['frm-spisplanForm'].submit();");
        }
        
        $form->addSubmit('go_spisplan', 'Zobrazit')
                 ->setRendered(TRUE)
                 ->onClick[] = array($this, 'spisplanClicked');

        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function spisplanClicked(SubmitButton $button)
    {
        $form_data = $button->getForm()->getValues();
        $session_spisplan = Environment::getSession('s3_spisplan');
        $session_spisplan->spis_id = $form_data['spisplan'];

        $action = $this->getAction();
        if ( $action == "vyber" ) {
            // is ajax
            $this->forward('vyber', array('spisplan_id'=>$form_data['spisplan'],'id'=>$form_data['dokument_id'],'is_ajax'=>1) );
        } else {
            $this->forward('default', array('id'=>$form_data['spisplan']) );
        }
        
    }


    public function actionZmenitspisovyznak()
    {
        $Spis = new Spis();
        $spisovy_znak_max = $Spis->maxSpisovyZnak( $this->getParam('id',null) );
        echo $spisovy_znak_max;
        exit;
    }

    protected function createComponentSearchForm()
    {

        $hledat =  !is_null($this->hledat)?$this->hledat:'';

        $form = new AppForm();
        $form->addText('dotaz', 'Hledat:', 20, 100)
                 ->setValue($hledat);
        $form['dotaz']->getControlPrototype()->title = "Hledat lze dle názvu spisu";

        $form->addSubmit('hledat', 'Hledat')
                 ->onClick[] = array($this, 'hledatSimpleClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function hledatSimpleClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $this->forward('this', array('hledat'=>$data['dotaz']));

    }


}

