<?php

namespace Spisovka;

class AclModel extends BaseModel
{

    protected $name = 'acl_role_to_privilege';
    protected $tb_resource = 'acl_resource';
    protected $tb_role = 'acl_role';
    protected $tb_rule = 'acl_privilege';

    public function getResources($all = 0)
    {
        $cols = ($all == 1) ? '*' : 'code';

        $res = DbCache::get('s3_Resource_' . $cols);
        if ($res !== null)
            return $res;

        $res = dibi::fetchAll('SELECT ' . $cols . ' FROM [' . $this->tb_resource . '] ORDER BY code ASC');

        DbCache::set('s3_Resource_' . $cols, $res);

        return $res;
    }

    public function getPermission()
    {
        $res = DbCache::get('s3_Permission');
        if ($res !== null)
            return $res;

        $res = dibi::fetchAll('
                SELECT
                    a.allowed as allowed,
                    ro.code as role,
                    re.code as resource,
                    ru.privilege as privilege
                    FROM [' . $this->name . '] a
                    JOIN [' . $this->tb_role . '] ro ON (a.role_id = ro.id)
                    JOIN [' . $this->tb_rule . '] ru ON (a.privilege_id = ru.id)
                    LEFT JOIN [' . $this->tb_resource . '] re ON (ru.resource_id = re.id)

                    ORDER BY ro.fixed DESC, a.allowed DESC, ro.code, ru.privilege
        ');

        DbCache::set('s3_Permission', $res);

        return $res;
    }

    public function hledatPravidlo($data)
    {
        $where = array();
        if (isset($data['privilege']))
            $where[] = array('ru.privilege=%s', $data['privilege']);

        $rows = dibi::fetchAll('
            SELECT
                ru.*,
                re.code resource_code,
                re.name resource_name,
                re.note resource_note
                FROM [' . $this->tb_rule . '] ru
                LEFT JOIN [' . $this->tb_resource . '] re ON (ru.resource_id = re.id)
                %ex', (!empty($where) ? array('WHERE %and', $where) : NULL),
                        'ORDER BY re.code ASC'
        );

        return ($rows) ? $rows : null;
    }

    public function seznamPravidel(Role $role, \Nette\Security\IAuthorizator $authorizator)
    {
        $rows = dibi::fetchAll('
            SELECT
                ru.*,
                re.code resource_code,
                re.name resource_name,
                re.note resource_note
                FROM [' . $this->tb_rule . '] ru
                LEFT JOIN [' . $this->tb_resource . '] re ON (ru.resource_id = re.id)
                ORDER BY re.code, ru.order
        ');

        $tmp = array();

        foreach ($rows as $pravidlo) {

            if (strpos($pravidlo->privilege, 'orgjednotka_') !== false) {
                continue;
            }

            $resource_id = is_null($pravidlo->resource_id) ? 0 : $pravidlo->resource_id;

            if (is_null($pravidlo->resource_name)) {
                $tmp[$resource_id]['name'] = "Základní pravidla";
                $tmp[$resource_id]['code'] = ":";
                $tmp[$resource_id]['note'] = "";
            } else {
                $tmp[$resource_id]['name'] = $pravidlo->resource_name;
                $tmp[$resource_id]['code'] = $pravidlo->resource_code;
                $tmp[$resource_id]['note'] = $pravidlo->resource_note;
            }

            //$tmp[ $resource_id ]['pravidla'][ $pravidlo->privilege_id ] = $pravidlo;
            $tmp[$resource_id]['pravidla'][$pravidlo->id]['name'] = $pravidlo->name;
            // Pozn.: Sloupec 'note' byl z tabulky odstanen
            $tmp[$resource_id]['pravidla'][$pravidlo->id]['note'] = '';
            $tmp[$resource_id]['pravidla'][$pravidlo->id]['resource'] = $pravidlo->resource_code;
            $tmp[$resource_id]['pravidla'][$pravidlo->id]['privilege'] = $pravidlo->privilege;

            $povoleno = $authorizator->isAllowed($role->code, $pravidlo->resource_code,
                    $pravidlo->privilege);
            if ($povoleno) {
                $povoleno = "ano";
            } else {
                $povoleno = "ne";
            }

            $tmp[$resource_id]['pravidla'][$pravidlo->id]['opravneni'] = $povoleno;
            $tmp[$resource_id]['pravidla'][$pravidlo->id]['role_id'] = null;
        }

        return $tmp;
    }

    public function seznamOpravneni($role = null)
    {
        $rows = dibi::query('
            SELECT
                a.allowed allowed,
                ro.code role,
                ro.id role_id,
                
                re.code resource,
                re.id resource_id,

                ru.privilege privilege,
                ru.id privilege_id


                FROM [' . $this->name . '] a
                JOIN [' . $this->tb_role . '] ro ON (a.role_id = ro.id)
                LEFT JOIN [' . $this->tb_rule . '] ru ON (a.privilege_id = ru.id)
                LEFT JOIN [' . $this->tb_resource . '] re ON (ru.resource_id = re.id)
                %if', !is_null($role), 'WHERE ro.code=%s', $role,
                        '
                ORDER BY re.code ASC
        ');

        return $rows->fetchAssoc('privilege_id');
    }

    public function insertAcl($data)
    {
        DbCache::delete('s3_Permission');
        dibi::insert($this->name, $data)
                        ->execute();
    }

    public function deleteAcl($where)
    {
        if (is_null($where))
            return null;

        DbCache::delete('s3_Permission');
        dibi::delete($this->name)->where($where)->execute();
    }

//    public function insertResource($data)
//    {
//        DbCache::delete('s3_Resource_*');
//        DbCache::delete('s3_Resource_code');
//        DbCache::delete('s3_Permission');
//
//        return dibi::insert($this->tb_resource, $data)
//                        ->execute(dibi::IDENTIFIER);
//    }
//
//    public function insertRule($data)
//    {
//        DbCache::delete('s3_Permission');
//
//        return dibi::insert($this->tb_rule, $data)
//                        ->execute(dibi::IDENTIFIER);
//    }

//    public function deleteResource($where)
//    {
//        if (is_null($where)) {
//            return null;
//        } else if (!is_array($where)) {
//            $where = array($where);
//        } else {
//            if (!is_array(current($where))) {
//                $where = array($where);
//            }
//        }
//
//        DbCache::delete('s3_Resource_*');
//        DbCache::delete('s3_Resource_code');
//        DbCache::delete('s3_Permission');
//
//        return dibi::delete($this->tb_resource)->where($where)->execute();
//    }
//
//    public function deleteRule($where)
//    {
//        if (is_null($where)) {
//            return null;
//        } else if (!is_array($where)) {
//            $where = array($where);
//        } else {
//            if (!is_array(current($where))) {
//                $where = array($where);
//            }
//        }
//
//        DbCache::delete('s3_Rule');
//        DbCache::delete('s3_Permission');
//
//        return dibi::delete($this->tb_rule)->where($where)->execute();
//    }

}
