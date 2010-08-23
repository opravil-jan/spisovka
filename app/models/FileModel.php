<?php

//'main','enclosure','signature','meta'

class FileModel extends BaseModel
{

    protected $name = 'file';
    protected $primary = 'file_id';


    public function getInfo($file_id, $file_version = null)
    {

        if ( !is_null($file_version) ) {
            $result = $this->fetchRow(array(
                                         array('file_id=%i',$file_id),
                                         array('file_version=%i',$file_version)
                                           )
                                     );
        } else {
            $result = $this->fetchAll(array('file_version'=>'DESC'),array(array('file_id=%i',$file_id)),null,1);
        }
        $row = $result->fetch();

        if ( $row ) {

            $UserModel = new UserModel();
            $user = $UserModel->getIdentity($row->user_created);
            $row->user_name = Osoba::displayName($user);
            $row->typ_name = FileModel::typPrilohy($row->typ, 1);

            return $row;
        } else {
            return null;
        }

    }

    public function seznam($vse=0, $dokument_id=null, $dokument_version=null) {

        $select = $this->fetchAll(array('nazev'));
        $rows = $select->fetchAll();

        $UserModel = new UserModel();
        $tmp = array();
        foreach ($rows as $file) {
            
            $user = $UserModel->getIdentity($file->user_created);
            $file->user_name = Osoba::displayName($user);
            $file->typ_name = FileModel::typPrilohy($file->typ, 1);

            $tmp[ $file->file_id ] = $file;


        }

        return ($rows) ? $rows : NULL;

    }

    public function vlozit($data) {

        $row = array();
        $row['typ'] = isset($data['typ'])?$data['typ']:1;
        $row['nazev'] = $data['nazev'];
        $row['popis'] = isset($data['popis'])?$data['popis']:'';
        $row['real_name'] = $data['real_name'];
        $row['real_path'] = $data['real_path'];
        $row['real_type'] = isset($data['real_type'])?$data['real_type']:'UploadFile_Basic';

        $row['mime_type'] = isset($data['mime_type'])?$data['mime_type']:FileModel::mimeType($row['real_path']);

        if ( !isset($data['md5_hash']) ) {
            if ( file_exists($data['real_path']) ) {
                $row['md5_hash'] = md5_file($data['real_path']);
            } else {
                $row['md5_hash'] = '';
            }
        } else {
            $row['md5_hash'] = $data['md5_hash'];
        }

        if ( !isset($data['size']) ) {
            if ( file_exists($data['real_path']) ) {
                $row['size'] = filesize($data['real_path']);
            } else {
                $row['size'] = -1;
            }
        } else {
            $row['size'] = $data['size'];
        }

        $row['date_created'] = new DateTime();
        $row['user_created'] = Environment::getUser()->getIdentity()->user_id;
        $row['guid'] = UUID::v4();

        // ulozeni
        $row['file_id'] = $this->max();
        $row['file_version'] = 1;
        $row['stav'] = 1;

        //Debug::dump($row); exit;

        if ( $this->insert_basic($row) ) {
            return $this->getInfo($row['file_id'], $row['file_version']);
        } else {
            return false;
        }

    }

    public function upravitMetadata($data, $file_id) {


        $file_info = $this->fetchAll(array('file_version'=>'DESC'),array(array('file_id=%i',$file_id)),null,1)->fetch();
        if ( !$file_info ) return false;

        $file_info = $this->obj2array($file_info);

        $row = $file_info;
        $row['typ'] = isset($data['typ'])?$data['typ']:1;
        $row['nazev'] = $data['nazev'];
        $row['popis'] = isset($data['popis'])?$data['popis']:'';

        $row['date_modified'] = new DateTime();
        $row['user_modified'] = Environment::getUser()->getIdentity()->user_id;

        // ulozeni
        $row['file_version'] = $row['file_version'] + 1;

        if ( $this->insert_basic($row) ) {
            return $this->getInfo($row['file_id'], $row['file_version']);
        } else {
            return false;
        }


    }

    protected function odebrat($data) {
        
    }

    protected function max() {
        $result = $this->fetchAll(array('file_id'=>'DESC'),null,null,1);
        $row = $result->fetch();
        return ($row) ? ($row->file_id+1) : 1;
    }

    public static function typPrilohy($typ=null , $out=0) {

        $enum_orig = array('1'=>'main',
                           '2'=>'enclosure',
                           '3'=>'signature',
                           '4'=>'meta',
                           '5'=>'source'
                     );
        $enum_popis = array('1'=>'hlavní soubor',
                            '2'=>'příloha',
                            '3'=>'podpis',
                            '4'=>'metadata',
                            '5'=>'zdrojový soubor'
                     );

        if ( is_null($typ) ) {
            return $enum_popis;
        }
        if ( $out == 0 ) {
            return ( array_key_exists($typ, $enum_orig) )?$enum_orig[ $typ ]:null;
        } else if ( $out == 1 ) {
            return ( array_key_exists($typ, $enum_popis) )?$enum_popis[ $typ ]:null;
        } else {
            return null;
        }

    }

    public static function copy($source, $destination) {

        if (!($handle_src = fopen($src, "rb")))
            return false;

        if (!($handle_dst = fopen($dst, "wb"))) {
            fclose($handle_src);
            return false;
        }

        if (flock($handle_dst, LOCK_EX)) {
            while (!feof($handle_src)) {
                if ($data = fread($handle_src, 1024)) {
                    if (!fwrite($handle_dst, $data))
                        return false;
                }
                else
                    return false;
            }
            if (!flock($handle_dst, LOCK_UN))
            return false;
        }
        else
            return false;

        if (!fclose($handle_src) || !fclose($handle_dst))
            return false;

        return true;
    }

    public static function mimeType($filename) {

        $mime_types = array(

            '' => 'application/octet-stream',
            '323' => 'text/h323',
            'acx' => 'application/internet-property-stream',
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'asf' => 'video/x-ms-asf',
            'asr' => 'video/x-ms-asf',
            'asx' => 'video/x-ms-asf',
            'au' => 'audio/basic',
            'avi' => 'video/x-msvideo',
            'axs' => 'application/olescript',
            'bas' => 'text/plain',
            'bcpio' => 'application/x-bcpio',
            'bin' => 'application/octet-stream',
            'bmp' => 'image/bmp',
            'bsr' => 'application/x-bsr',
            'c' => 'text/plain',
            'cat' => 'application/vnd.ms-pkiseccat',
            'cdf' => 'application/x-cdf',
            'cer' => 'application/x-x509-ca-cert',
            'class' => 'application/octet-stream',
            'clp' => 'application/x-msclip',
            'cmx' => 'image/x-cmx',
            'cod' => 'image/cis-cod',
            'cpio' => 'application/x-cpio',
            'crd' => 'application/x-mscardfile',
            'crl' => 'application/pkix-crl',
            'crt' => 'application/x-x509-ca-cert',
            'csh' => 'application/x-csh',
            'css' => 'text/css',
            'dcr' => 'application/x-director',
            'der' => 'application/x-x509-ca-cert',
            'dir' => 'application/x-director',
            'dll' => 'application/x-msdownload',
            'dms' => 'application/octet-stream',
            'doc' => 'application/msword',
            'dot' => 'application/msword',
            'dvi' => 'application/x-dvi',
            'dxr' => 'application/x-director',
            'eml' => 'message/rfc822',
            'eps' => 'application/postscript',
            'etx' => 'text/x-setext',
            'evy' => 'application/envoy',
            'exe' => 'application/octet-stream',
            'fif' => 'application/fractals',
            'flr' => 'x-world/x-vrml',
            'gif' => 'image/gif',
            'gtar' => 'application/x-gtar',
            'gz' => 'application/x-gzip',
            'h' => 'text/plain',
            'hdf' => 'application/x-hdf',
            'hlp' => 'application/winhlp',
            'hqx' => 'application/mac-binhex40',
            'hta' => 'application/hta',
            'htc' => 'text/x-component',
            'htm' => 'text/html',
            'html' => 'text/html',
            'htt' => 'text/webviewhtml',
            'ico' => 'image/x-icon',
            'ief' => 'image/ief',
            'iii' => 'application/x-iphone',
            'ins' => 'application/x-internet-signup',
            'isp' => 'application/x-internet-signup',
            'jfif' => 'image/pipeg',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'latex' => 'application/x-latex',
            'lha' => 'application/octet-stream',
            'lsf' => 'video/x-la-asf',
            'lsx' => 'video/x-la-asf',
            'lzh' => 'application/octet-stream',
            'm13' => 'application/x-msmediaview',
            'm14' => 'application/x-msmediaview',
            'm3u' => 'audio/x-mpegurl',
            'man' => 'application/x-troff-man',
            'mdb' => 'application/x-msaccess',
            'me' => 'application/x-troff-me',
            'mht' => 'message/rfc822',
            'mhtml' => 'message/rfc822',
            'mid' => 'audio/mid',
            'mny' => 'application/x-msmoney',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2' => 'video/mpeg',
            'mp3' => 'audio/mpeg',
            'mpa' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpp' => 'application/vnd.ms-project',
            'mpv2' => 'video/mpeg',
            'ms' => 'application/x-troff-ms',
            'mvb' => 'application/x-msmediaview',
            'nws' => 'message/rfc822',
            'oda' => 'application/oda',
            'p10' => 'application/pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7b' => 'application/x-pkcs7-certificates',
            'p7c' => 'application/x-pkcs7-mime',
            'p7m' => 'application/x-pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/x-pkcs7-signature',
            'pbm' => 'image/x-portable-bitmap',
            'pdf' => 'application/pdf',
            'pfx' => 'application/x-pkcs12',
            'pgm' => 'image/x-portable-graymap',
            'pko' => 'application/ynd.ms-pkipko',
            'pma' => 'application/x-perfmon',
            'pmc' => 'application/x-perfmon',
            'pml' => 'application/x-perfmon',
            'pmr' => 'application/x-perfmon',
            'pmw' => 'application/x-perfmon',
            'pnm' => 'image/x-portable-anymap',
            'png' => 'image/png',
            'pot,' => 'application/vnd.ms-powerpoint',
            'ppm' => 'image/x-portable-pixmap',
            'pps' => 'application/vnd.ms-powerpoint',
            'ppt' => 'application/vnd.ms-powerpoint',
            'prf' => 'application/pics-rules',
            'ps' => 'application/postscript',
            'pub' => 'application/x-mspublisher',
            'qt' => 'video/quicktime',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ras' => 'image/x-cmu-raster',
            'rgb' => 'image/x-rgb',
            'rmi' => 'audio/mid',
            'roff' => 'application/x-troff',
            'rtf' => 'application/rtf',
            'rtx' => 'text/richtext',
            'scd' => 'application/x-msschedule',
            'sct' => 'text/scriptlet',
            'setpay' => 'application/set-payment-initiation',
            'setreg' => 'application/set-registration-initiation',
            'sh' => 'application/x-sh',
            'shar' => 'application/x-shar',
            'sit' => 'application/x-stuffit',
            'snd' => 'audio/basic',
            'spc' => 'application/x-pkcs7-certificates',
            'spl' => 'application/futuresplash',
            'src' => 'application/x-wais-source',
            'sst' => 'application/vnd.ms-pkicertstore',
            'stl' => 'application/vnd.ms-pkistl',
            'stm' => 'text/html',
            'svg' => 'image/svg+xml',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc' => 'application/x-sv4crc',
            'swf' => 'application/x-shockwave-flash',
            't' => 'application/x-troff',
            'tar' => 'application/x-tar',
            'tcl' => 'application/x-tcl',
            'tex' => 'application/x-tex',
            'texi' => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'tgz' => 'application/x-compressed',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'tr' => 'application/x-troff',
            'trm' => 'application/x-msterminal',
            'tsv' => 'text/tab-separated-values',
            'txt' => 'text/plain',
            'uls' => 'text/iuls',
            'ustar' => 'application/x-ustar',
            'vcf' => 'text/x-vcard',
            'vrml' => 'x-world/x-vrml',
            'wav' => 'audio/x-wav',
            'wcm' => 'application/vnd.ms-works',
            'wdb' => 'application/vnd.ms-works',
            'wks' => 'application/vnd.ms-works',
            'wmf' => 'application/x-msmetafile',
            'wps' => 'application/vnd.ms-works',
            'wri' => 'application/x-mswrite',
            'wrl' => 'x-world/x-vrml',
            'wrz' => 'x-world/x-vrml',
            'xaf' => 'x-world/x-vrml',
            'xbm' => 'image/x-xbitmap',
            'xla' => 'application/vnd.ms-excel',
            'xlc' => 'application/vnd.ms-excel',
            'xlm' => 'application/vnd.ms-excel',
            'xls' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',
            'xlw' => 'application/vnd.ms-excel',
            'xof' => 'x-world/x-vrml',
            'xpm' => 'image/x-xpixmap',
            'xwd' => 'image/x-xwindowdump',
            'z' => 'application/x-compress',
            'fo' => 'application/vnd.software602.filler.form+xml',
            'zfo' => 'application/vnd.software602.filler.form-xml-zip',
            'zip' => 'application/zip'

        );

        if ( preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix) ) {
            $ext = strtolower($fileSuffix[1]);
        } else {
            $ext = @strtolower(array_pop(explode('.',$filename)));
        }

        if ( array_key_exists($ext, $mime_types) ) {
            return $mime_types[$ext];
        //} elseif ( function_exists('finfo_open') ) {
        //    $finfo = finfo_open(FILEINFO_MIME);
        //    $mimetype = finfo_file($finfo, $filename);
        //    finfo_close($finfo);
        //    return $mimetype;
        } else if(function_exists("mime_content_type")) {
            $fileSuffix = @mime_content_type($filename);
            return ( $fileSuffix )?trim($fileSuffix[0]):'application/octet-stream';
        } else {
            return 'application/octet-stream';
        }

    }



}