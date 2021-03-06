<?php

namespace Spisovka;

use Nette;

class Authenticator_UI extends Nette\Application\UI\Control
{

    protected $authenticator;
    protected $httpRequest;
    protected $userImport;
    protected $action;
    protected $form_params = [];  // promenna 'params' je jiz pouzita k jinemu ucelu

    // nutne kvuli vytvoreni sluzby pomoci Nette DI

    public function __construct(Authenticator_Basic $authenticator, Nette\Http\IRequest $httpRequest, IUserImport $userImport
    = null)
    {
        parent::__construct();
        $this->authenticator = $authenticator;
        $this->httpRequest = $httpRequest;
        $this->userImport = $userImport;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function setParams($params)
    {
        $this->form_params = $params;
        return $this;
    }

    public function render()
    {
        $t = null;
        $form = null;
        switch ($this->action) {
            case 'login':
                $t = '/auth_login.phtml';
                break;
            case 'change_auth':
                $form = $this->getComponent('changeAuthTypeForm');
                break;
            case 'change_password':
                // Toto byla zásadní chyba: Metodu createComponent... nevolat ručně,
                // protože může dojít k pokusu o dvounásobné vytvoření (např. při submitu)
                // $form = $this->createComponentChangePasswordForm('changePasswordForm');
                $form = $this->getComponent('changePasswordForm');
                break;
            case 'new_user':
                $form = $this->getComponent('newUserForm');
                break;
            case 'sync':
                $form = $this->getComponent('syncForm');
                break;
            default:
                break;
        }
        if ($t) {
            $this->template->setFile(dirname(__FILE__) . $t);
            $this->template->render();
        } elseif ($form)
            $form->render();
    }

    protected function createComponentLoginForm($name)
    {
        $form = new Nette\Application\UI\Form($this, $name);
        $form->addText('username', 'Uživatelské jméno:')
                ->addRule(Nette\Forms\Form::FILLED, 'Zadejte uživatelské jméno, nebo e-mail.');

        $form->addPassword('password', 'Heslo:')
                ->addRule(Nette\Forms\Form::FILLED, 'Zadejte přihlašovací heslo.');

        $form->addHidden('backlink');
        if (!$form->isSubmitted()) {
            $url = $this->httpRequest->url->getAbsoluteUrl();
            $form['backlink']->setValue($url);
        }

        $form->addSubmit('login', 'Přihlásit');
        $form->onSuccess[] = array($this, 'handleLogin');
        $form->addProtection('Prosím přihlašte se znovu.');

        return $form;
    }

    protected function createComponentNewUserForm($name)
    {
        $form = new Form($this, $name);

        $form->addHidden('osoba_id');
        if (isset($this->form_params['osoba_id']))
            $form['osoba_id']->setValue($this->form_params['osoba_id']);

        $form->addText('username', 'Uživatelské jméno:', 30, 150);
        $form['username']->addRule(Nette\Forms\Form::FILLED,
                'Uživatelské jméno musí být vyplněno!');

        try {
            $user_list = $this->getPossibleUsers();
            if (!empty($user_list))
                $user_list = ['' => 'můžete vybrat ze seznamu'] + $user_list;
        } catch (\Exception $e) {
            $user_list = ['' => $e->getMessage()];
        }

        if (!empty($user_list)) {
            $form->addSelect('username_list', "Uživatelé z externího zdroje:", $user_list)
            ->controlPrototype->onchange('$("[name=username]").val($(this).val())');
        }

        $this->formAddAuthSelect($form);

        $form->addPassword('heslo', 'Heslo:', 30, 30);
        $form->addPassword('heslo_potvrzeni', 'Heslo znovu:', 30, 30)
                ->setRequired(false)
                ->addRule(Nette\Forms\Form::EQUAL, "Hesla se musí shodovat!", $form["heslo"]);

        $this->formAddRoleSelect($form);
        $this->formAddOrgSelect($form);

        if (!$this->authenticator->supportsRemoteAuth()) {
            $form['heslo']->setRequired('Heslo musí být vyplněné.');
            $form['heslo_potvrzeni']->setRequired('Potvrzení hesla musí být vyplněné.');
        } else {
            $form['heslo']->addConditionOn($form["external_auth"], Nette\Forms\Form::NOT_EQUAL,
                            1)
                    ->addRule(Nette\Forms\Form::FILLED, 'Heslo musí být vyplněné.');
            if (!$form['heslo']->getValue()) {
                $form['heslo']->setDisabled();
                $form['heslo_potvrzeni']->setDisabled();
            }
            $form['external_auth']->controlPrototype->onchange(
                    'var dis = $(this).val() == 1; $("[name=heslo]").prop("disabled", dis);'
                    . '$("[name=heslo_potvrzeni]").prop("disabled", dis);');
        }
        $form->addSubmit('new_user', 'Vytvořit účet')
                ->onClick[] = array($this, 'handleNewUser');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'handleCancel');

        return $form;
    }

    protected function createComponentSyncForm($name)
    {
        if (!$this->userImport) {
            echo '<div class="prazdno">';
            echo 'Import uživatelů není nakonfigurován.';
            echo "</div>";
            return null;
        }

        $form = new Nette\Application\UI\Form($this, $name);

        try {
            $seznam = $this->userImport->getRemoteUsers();
        } catch (\Exception $e) {
            echo '<div class="prazdno">';
            echo $e->getMessage();
            echo '<p>';
            echo 'Zkontrolujte prosím, že konfigurace je správná.';
            echo "</div>";
            return null;
        }

        if (is_array($seznam) && !empty($seznam)) {
            if (!$form->isSubmitted()) {
                echo "<div>Vyberte zaměstnance, které chcete přidat do aplikace a zvolte"
                . " jejich roli.<br /><br /></div><br />";
            }

            $existing_users = array_flip(UserAccount::getAllUserNames());

            foreach ($seznam as $id => $user) {

                if (!isset($user['jmeno']))
                    $user['jmeno'] = '';
                if (!isset($user['email']))
                    $user['email'] = '';

                $form->addGroup('');
                $cont = $form->addContainer("user_$id");

                if (!isset($existing_users[$user['username']])) {
                    $cont->addCheckbox('add', 'Přidat');
                    $cont->addText('username', "Uživatelské jméno:")
                            ->addRule(Nette\Forms\Form::FILLED,
                                    'Uživatelské jméno musí být vyplněné.')
                            ->setValue($user['username']);
                    $cont->addText('prijmeni', 'Příjmení:')
                            ->addRule(Nette\Forms\Form::FILLED, 'Příjmení musí být vyplněné.')
                            ->setValue($user['prijmeni']);
                    $cont->addText('jmeno', 'Jméno:')
                            ->setValue($user['jmeno']);
                    $cont->addText('email', 'E-mail:')
                            ->setValue($user['email'])
                            ->addCondition(Nette\Forms\Form::FILLED)
                            ->addRule(Nette\Forms\Form::EMAIL);

                    $this->formAddRoleSelect($cont);
                    $this->formAddOrgSelect($cont);

                    $cont['email']->getControlPrototype()->style(['width' => '170px']);
                    $cont['role']->getControlPrototype()->style(['width' => '110px']);
                    $cont['orgjednotka_id']->getControlPrototype()->style(['width' => '130px']);
                } else {
                    $cont->addCheckbox('add', 'Již existuje')
                            ->setDisabled();
                    $cont->addText('username', "Uživatelské jméno:")
                            ->setValue($user['username']);
                    $cont->addText('prijmeni', 'Příjmení:')
                            ->setValue($user['prijmeni']);
                    $cont->addText('jmeno', 'Jméno:')
                            ->setValue($user['jmeno']);
                }

                $cont['username']->getControlPrototype()->style(['width' => '100px']);
                $cont['prijmeni']->getControlPrototype()->style(['width' => '100px']);
                $cont['jmeno']->getControlPrototype()->style(['width' => '70px']);
            }


            $form->addGroup('');
            $form->addSubmit('pridat', 'Přidat');
            $form->onSuccess[] = array($this, 'handleSync');

            $renderer = $form->getRenderer();

            $renderer->wrappers['form']['container'] = "table";
            $renderer->wrappers['group']['container'] = "tr";
            $renderer->wrappers['group']['label'] = null;
            $renderer->wrappers['controls']['container'] = null;
            $renderer->wrappers['pair']['container'] = 'td';
            $renderer->wrappers['label']['container'] = null;
            $renderer->wrappers['control']['container'] = null;
        } else {
            echo '<div class="prazdno">';
            echo 'Nenalezl jsem žádné uživatele.';
            echo '<p>';
            echo 'Zkontrolujte prosím, že konfigurace je správná.';
            echo "</div>";
        }

        return $form;
    }

    protected function createComponentChangePasswordForm($name)
    {
        $form = new Form($this, $name);

        $form->addHidden('osoba_id');
        $form->addHidden('user_id');

        $params = $this->form_params;
        if (isset($params['osoba_id']))
            $form['osoba_id']->setValue($params['osoba_id']);
        if (isset($params['user_id']))
            $form['user_id']->setValue($params['user_id']);

        $form->addPassword('heslo', 'Heslo:', 30, 30)
                ->addRule(Nette\Forms\Form::FILLED, 'Heslo musí být vyplněné.');
        $form->addPassword('heslo_potvrzeni', 'Heslo znovu:', 30, 30)
                ->addRule(Nette\Forms\Form::FILLED, 'Heslo musí být vyplněné.')
                ->addConditionOn($form["heslo"], Nette\Forms\Form::FILLED)
                ->addRule(Nette\Forms\Form::EQUAL, "Hesla se musí shodovat!", $form["heslo"]);

        $form->addSubmit('change_password', 'Změnit heslo')
                ->onClick[] = array($this, 'handleChangePassword');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'handleCancel');

        return $form;
    }

    protected function createComponentChangeAuthTypeForm($name)
    {
        $form = new Form($this, $name);

        $form->addHidden('osoba_id');
        $form->addHidden('user_id');

        $params = $this->form_params;
        if (isset($params['osoba_id']))
            $form['osoba_id']->setValue($params['osoba_id']);

        $auth_type = null;
        if (isset($params['user_id'])) {
            $form['user_id']->setValue($params['user_id']);
            $user_info = new UserAccount($params['user_id']);
            $auth_type = $user_info->external_auth;
        }

        $this->formAddAuthSelect($form, $auth_type);

        $form->addSubmit('change_auth', 'Změnit ověření')
                ->onClick[] = array($this, 'handleChangeAuthType');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'handleCancel');

        return $form;
    }

    public function handleCancel(Nette\Forms\Controls\SubmitButton $button)
    {
        $this->presenter->redirect('this', ['upravit' => null]);
    }

    public function handleLogin(Nette\Application\UI\Form $form, $data)
    {
        try {
            $this->presenter->user->login($data['username'], $data['password']);

            $this->afterLogin();
            $redirect_home = (bool) Settings::get('login_redirect_homepage', false);
            $url = isset($data['backlink']) ? $data['backlink'] : '';
            if (!$redirect_home && !empty($url)) {
                $this->presenter->redirectUrl($url);
            } else
                $this->presenter->redirect(':Spisovka:Default:default');
        } catch (Nette\Security\AuthenticationException $e) {
            $this->presenter->flashMessage($e->getMessage(), 'warning');
            sleep(2); // sniz riziko brute force utoku
        }
    }

    protected function afterLogin()
    {
        // osetri vynucene odhlaseni, aby uzivatel nebyl okamzite po prihlaseni zase odhlasen
        $a = new UserAccount($this->presenter->user->id);
        if ($a->force_logout) {
            $a->force_logout = false;
            $a->save();
        }

        /* Pokus o pridani upozorneni se nezdaril, protoze je problem zobrazit jakoukoli
         * flash zpravu (kvuli pouziti redirectUrl() po prihlaseni)
          // Zkontroluj, zda ma uzivatel predane dokumenty
          $Dokument = new Dokument;
          $args_f = $Dokument->fixedFiltr('kprevzeti', false, false);
          $args = $Dokument->spisovka($args_f);
          $result = $Dokument->seznam($args);

          if (count($result))
          $this->presenter->flashMessage('Máte dokument(y) k převzetí. Počet dokumentů: ' . count($result), 'info');

         */
    }

    protected function formAddAuthSelect(Nette\Forms\Container $form, $value = null)
    {
        if ($this->authenticator->supportsRemoteAuth()) {
            $form->addSelect('external_auth', "Způsob ověření hesla:",
                    array(1 => 'externí ověření',
                0 => 'lokální ověření spisovkou'
            ));
            if ($value !== null)
                $form['external_auth']->setDefaultValue($value);
        }
    }

    protected function formAddRoleSelect(Nette\Forms\Container $form)
    {
        static $default_role;
        static $role_list;

        $Role = new RoleModel();
        if (!$default_role)
            $default_role = $Role->getDefaultRole();
        if (!$role_list)
            $role_list = $Role->seznam();

        $form->addSelect('role', 'Role:', $role_list)
                ->setDefaultValue($default_role);
    }

    protected function formAddOrgSelect(Nette\Forms\Container $form)
    {
        static $select;

        if (!$select) {
            $m = new OrgJednotka;
            $seznam = $m->linearniSeznam();
            $select = array(0 => 'žádná');
            foreach ($seznam as $org)
                $select[$org->id] = $org->ciselna_rada . ' - ' . $org->zkraceny_nazev;
        }

        $form->addSelect('orgjednotka_id', 'Organizační jednotka:', $select);
    }

    // Přidá uživatelský účet existující osobě
    public function handleNewUser(Nette\Forms\Controls\SubmitButton $button)
    {
        $fd = $button->getForm()->getValues(); // form data

        if (!isset($fd['osoba_id']))
            $this->presenter->redirect('this');

        $account_data = [
            'username' => $fd['username'],
            'orgjednotka_id' => !empty($fd['orgjednotka_id']) ? $fd['orgjednotka_id'] : null
        ];
        if (isset($fd['heslo']))
            $account_data['password'] = $fd['heslo'];
        if (isset($fd['external_auth']))
            $account_data['external_auth'] = $fd['external_auth'];

        $this->createUserAccount($fd['osoba_id'], $account_data, $fd['role']);

        $this->presenter->redirect('this');
    }

    public function handleChangePassword(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $ua = new UserAccount($data['user_id']);
        if ($ua->changePassword($data['heslo']))
            $this->presenter->flashMessage('Heslo úspěšně změněno.');
        else
            $this->presenter->flashMessage('Heslo není možné změnit.', 'warning');

        $this->presenter->redirect('this', ['upravit' => null]);
    }

    public function handleChangeAuthType(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $ua = new UserAccount($data['user_id']);
        $ua->external_auth = $data['external_auth'];
        $ua->last_modified = new \DateTime();
        $ua->save();

        $this->presenter->flashMessage('Nastavení změněno.');
        $this->presenter->redirect('this');
    }

    public function handleSync(Nette\Application\UI\Form $form, $data)
    {
        $users_added = 0;
        $users_failed = 0;

        foreach ($data as $row)
            if (isset($row['add']) && $row['add'] === true) {

                $person = ['jmeno' => $row['jmeno'],
                    'prijmeni' => $row['prijmeni'],
                    'email' => $row['email']
                ];
                if (isset($row['titul_pred']))
                    $person['titul_pred'] = $row['titul_pred'];
                if (isset($row['telefon']))
                    $person['telefon'] = $row['telefon'];
                if (isset($row['pozice']))
                    $person['pozice'] = $row['pozice'];

                $account_data = [
                    'username' => $row['username'],
                    'external_auth' => 1,
                    'orgjednotka_id' => !empty($row['orgjednotka_id']) ? $row['orgjednotka_id']
                        : null
                ];
                $success = $this->createUserAccount($person, $account_data, $row['role']);
                if ($success)
                    $users_added++;
                else
                    $users_failed++;
            }

        if ($users_added)
            $this->presenter->flashMessage("Bylo přidáno $users_added zaměstnanců.");
        else
            $this->presenter->flashMessage('Nebyli přidáni žádní zaměstnanci.');
        if ($users_failed)
            $this->presenter->flashMessage("$users_failed zaměstnanců se nepodařilo přidat.",
                    'warning');

        $this->presenter->redirect('this');
    }

    /**
     * Vytvoří uživatelský účet a případně i entitu osoby.
     * @param int|array $person_data id osoby nebo pole
     * @param array $account_data   data účtu
     * @param int $role_id 
     * @return boolean              úspěch operace
     */
    public function createUserAccount($person_data, $account_data, $role_id)
    {
        dibi::begin();
        try {
            $person_id = is_numeric($person_data) ? $person_data : Person::create($person_data)->id;
            $account_data['osoba_id'] = $person_id;
            $account = UserAccount::create($account_data);

            $User2Role = new User2Role();
            $row = ['role_id' => $role_id,
                'user_id' => $account->id,
                'date_added' => new \DateTime()
            ];
            $User2Role->insert($row);

            $this->presenter->flashMessage("Účet uživatele \"$account->username\" byl úspěšně vytvořen.");

            dibi::commit();
            return true;
        } catch (\Exception $e) {
            dibi::rollback();
            if ($e->getCode() == 1062) {
                $this->presenter->flashMessage("Uživatelský účet s názvem \"{$account_data['username']}\" již existuje. Zvolte jiný název.",
                        'warning');
            } else {
                $this->presenter->flashMessage('Účet uživatele se nepodařilo vytvořit.',
                        'warning');
                $this->presenter->flashMessage('Chyba: ' . $e->getMessage(), 'warning');
            }

            return false;
        }
    }

    protected function getPossibleUsers()
    {
        $remote_users = $this->userImport ? $this->userImport->getRemoteUsers() : null;
        $users = array();
        if (is_array($remote_users)) {
            $existing_users = array_flip(UserAccount::getAllUserNames());
            foreach ($remote_users as $user) {
                $username = $user['username'];
                if (!isset($existing_users[$username]))
                    $users[$username] = "$username - {$user['prijmeni']} {$user['jmeno']}";
            }
        }

        return $users;
    }

}
