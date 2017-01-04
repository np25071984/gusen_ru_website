<?php
define('UNIT_QUERY', <<<EOF
SELECT 
u.id AS id,
u.name AS name,
u.description AS description,
u.price AS price,
u.year AS year,
u.mileage AS mileage,
u.op_time AS op_time,
cities.id AS city_id,
cities.name AS city,
regions.id AS region_id,
regions.name AS region,
fdistricts.id AS fd_id,
fdistricts.name AS fdistrict,
fdistricts.short_name AS fdistrict_short,
categories.id AS cat_id,
categories.name AS category,
manufacturers.id AS manufacturer_id,
manufacturers.name AS manufacturer,
(SELECT img FROM images i WHERE i.unit_id=u.id ORDER BY `ORDER` ASC LIMIT 1) as img
FROM units u
JOIN cities ON u.city_id=cities.id
JOIN regions ON cities.rd_id=regions.id
JOIN fdistricts ON regions.fd_id=fdistricts.id
JOIN categories ON u.cat_id=categories.id
JOIN manufacturers ON manufacturers.id=u.manufacturer_id
EOF
);


/**
 * CModule class
 * 
 * A page template can contain any amount of modules. They are described 
 *  by this way:
 * 
 * %{MOD_NAME&XSL_TEMPLATE[&PARAM_1[&PARAM_2]]}%
 * 
 * @param CDataBase $hDbConn
 * @param string $modName
 * @param string $xslFile
 * @param1 string $param1
 * @param2 string $param2
 * 
 * @return void
 */
class CModule
{
    protected $xslDoc = null;
    protected $xmlDoc = null;

    function __construct(&$hDbConn, $modName, $xslFile, $param1, $param2) {
        $this->hDbConn = $hDbConn;
        $this->modName = $modName;
        $this->xslFile = $xslFile;
        $this->param1 = $param1;
        $this->param2 = $param2;
        
        $this->content = '';
        
        if ($this->xslFile != 'null') {
            if (file_exists("xsl/".$this->xslFile)) {
                $this->xslDoc = new DOMDocument();
                $this->xslDoc->load("xsl/".$this->xslFile);
    
                $this->xmlDoc = new DOMDocument('1.0', 'utf-8');
                $eRoot = $this->xmlDoc->createElement('root');
                $this->eRoot = $this->xmlDoc->appendChild($eRoot);
            }
            else {
                echo "XSL file haven't found!";
                exit;
            }
        }

        switch ($this->modName) {
            case "main_page_unit_list":
                $this->mainPageList();
                break;
            case "unit_page_unit":
                $this->unitPageMain();
                break;
            case "search_page_unit_list":
                $this->searchPageMain();
                break;
            case "unit_list_paginator":
                $this->searchPaginator($param1);
                break;
            case "user":
                $this->user();
                break;
            case "unit_comments":
                $this->userComments();
                break;
            case "admin_unit_form":
                $this->unitForm();
                break;
            case "search_form":
                $this->searchForm();
                break;
            case "comments_unapproved_total":
                $this->commentsUnapprovedTotal();
                break;
            case "admin_comments_list":
                $this->adminCommentsList();
                break;
            case "title":
                $this->title($this->param1);
                break;
        }
    }
    
    function __destruct () {
        unset($this->xslDoc);
        unset($this->xmlDoc);
    }  
    
    /**
     * Builds page navigation in CModule::xmlDoc
     * 
     * <?xml version="1.0" encoding="utf-8"?>
     * <root>
     *     <page type="first">
     *         <number><</number>
     *         <link>?page=search&offset=1</link>
     *     </page>
     *     <page current="regular">
     *         <number>10</number>
     *         <link>?page=search&offset=10</link>
     *      </page>
     *     <page current="current">
     *         <number>11</number>
     *         <link>?page=search&offset=11</link>
     *      </page>
     *      ...
     *     <page current="last">
     *         <number>68</number>
     *         <link>?page=search&offset=68</link>
     *      </page>
     * </root>
     * 
     * Which is limited by *$nav_show_pages* around current page
     * number *$page_cur*
     *
     * @param string $link_pattern
     * @param int $units_total
     * @param int $units_on_page
     * @param int $page_cur
     * @param int $nav_show_pages
     *
     * @return void
     * 
     */
    function paginator(
        $link_pattern, 
        $units_total, 
        $units_on_page, 
        $page_cur, 
        $nav_show_pages) 
    {
        // must be odd due to symmetric
        $nav_show = round($nav_show_pages, 0, PHP_ROUND_HALF_ODD);

        $iPagesTotal = ceil($units_total/$units_on_page);

        if ($iPagesTotal>1) {
            $iStartPage = max($page_cur-(($nav_show_pages-1)/2), 1);
            $iEndPage = $iStartPage + $nav_show_pages - 1;
            if ($iEndPage > $iPagesTotal) {
                $iEndPage = $iPagesTotal;
                $iStartPage = max($iEndPage - $nav_show_pages + 1, 1);
            }
            if ($iStartPage > 1) {
                $ePage = $this->xmlDoc->createElement('page');
                $ePage = $this->eRoot->appendChild($ePage);
                $eNumber = $this->xmlDoc->createElement('number', 1);
                $ePage->appendChild($eNumber);
                $eLink = $this->xmlDoc->createElement('link', sprintf($link_pattern, 1));
                $ePage->appendChild($eLink);
                $eIsCurrent = $this->xmlDoc->createAttribute('type');
                $eIsCurrent->value = 'first';
                $ePage->appendChild($eIsCurrent);
            }
            
            for ($i=$iStartPage; $i<=$iEndPage; $i++) {
                $ePage = $this->xmlDoc->createElement('page');
                $ePage = $this->eRoot->appendChild($ePage);
                $eNumber = $this->xmlDoc->createElement('number', $i);
                $ePage->appendChild($eNumber);
                $eLink = $this->xmlDoc->createElement('link', sprintf($link_pattern, $i));
                $ePage->appendChild($eLink);
                $eIsCurrent = $this->xmlDoc->createAttribute('type');
                if ($i == $page_cur) {
                    $eIsCurrent->value = 'current';
                }
                else {
                    $eIsCurrent->value = 'regular';
                }
                $ePage->appendChild($eIsCurrent);
            }
            if ($iEndPage < $iPagesTotal) {
                $ePage = $this->xmlDoc->createElement('page');
                $ePage = $this->eRoot->appendChild($ePage);
                $eNumber = $this->xmlDoc->createElement('number', $iEndPage);
                $ePage->appendChild($eNumber);
                $eLink = $this->xmlDoc->createElement('link', sprintf($link_pattern, $iEndPage));
                $ePage->appendChild($eLink);
                $eIsCurrent = $this->xmlDoc->createAttribute('type');
                $eIsCurrent->value = 'last';
                $ePage->appendChild($eIsCurrent);
            }
        }
    }

    function fillUnit($dbRes) {
  
        $top = $this->xmlDoc->createElement('unit');
        $top = $this->eRoot->appendChild($top);
        $topAttr = $this->xmlDoc->createAttribute('id');
        $topAttr->value = htmlentities($dbRes['id']);
        $top->appendChild($topAttr);
        $topAttr = $this->xmlDoc->createAttribute('name');
        $topAttr->value = htmlentities($dbRes['name']);
        $top->appendChild($topAttr);

        $sub = $this->xmlDoc->createElement('description',
            htmlentities($dbRes['description']));
        $top->appendChild($sub);
        $sub = $this->xmlDoc->createElement('price',
            htmlentities($dbRes['price']));
        $top->appendChild($sub);
        $sub = $this->xmlDoc->createElement('year',
            htmlentities($dbRes['year']));
        $top->appendChild($sub);


        $sub = $this->xmlDoc->createElement('category',
            htmlentities($dbRes['category']));
        $subAttr = $this->xmlDoc->createAttribute('id');
        $subAttr->value = htmlentities($dbRes['cat_id']);
        $sub->appendChild($subAttr);
        $top->appendChild($sub);
        
        $sub = $this->xmlDoc->createElement('fdistrict',
            htmlentities($dbRes['fdistrict']));
        $subAttr = $this->xmlDoc->createAttribute('id');
        $subAttr->value = htmlentities($dbRes['fd_id']);
        $sub->appendChild($subAttr);
        $subAttr = $this->xmlDoc->createAttribute('short');
        $subAttr->value = htmlentities($dbRes['fdistrict_short']);
        $sub->appendChild($subAttr);
        $top->appendChild($sub);
  
        $sub = $this->xmlDoc->createElement('region',
            htmlentities($dbRes['region']));
        $subAttr = $this->xmlDoc->createAttribute('id');
        $subAttr->value = htmlentities($dbRes['region_id']);
        $sub->appendChild($subAttr);
        $top->appendChild($sub);
  
        $sub = $this->xmlDoc->createElement('city',
            htmlentities($dbRes['city']));
        $subAttr = $this->xmlDoc->createAttribute('id');
        $subAttr->value = htmlentities($dbRes['city_id']);
        $sub->appendChild($subAttr);
        $top->appendChild($sub);

        $sub = $this->xmlDoc->createElement('manufacturer',
            htmlentities($dbRes['manufacturer']));
        $subAttr = $this->xmlDoc->createAttribute('id');
        $subAttr->value = htmlentities($dbRes['manufacturer_id']);
        $sub->appendChild($subAttr);
        $top->appendChild($sub);

        if (isset($dbRes['mileage'])) {
            $sub = $this->xmlDoc->createElement('mileage',
                htmlentities($dbRes['mileage']));
            $top->appendChild($sub);
        }
        if (isset($dbRes['op_time'])) {
            $sub = $this->xmlDoc->createElement('op_time',
                htmlentities($dbRes['op_time']));
            $top->appendChild($sub);
        }

        $sub = $this->xmlDoc->createElement('img',
            htmlentities($dbRes['img']));
        $top->appendChild($sub);
        
        return $top;
    }    
    
    function fillUser() {
        $sUser = $this->xmlDoc->createElement("user");
        $sUser = $this->eRoot->appendChild($sUser);
        $sUserAttr = $this->xmlDoc->createAttribute('name');
        $sUserAttr->value = $_SESSION["user"]["name"];
        $sUser->appendChild($sUserAttr);
        $sUserAttr = $this->xmlDoc->createAttribute('type');
        $sUserAttr->value = $_SESSION["user"]["type"];
        $sUser->appendChild($sUserAttr);
        $sUserAttr = $this->xmlDoc->createAttribute('id');
        $sUserAttr->value = $_SESSION["user"]["id"];
        $sUser->appendChild($sUserAttr);
        
        $sUserData = $this->xmlDoc->createElement("img", htmlentities($_SESSION["user"]["img"]));
        $sUser->appendChild($sUserData);

        $this->eRoot->appendChild($sUser); 
    }
    
    function execute() {
        if ($this->xslFile != 'null') {
            $hProc = new XSLTProcessor();
            $hProc->importStylesheet($this->xslDoc);
            return $hProc->transformToXML($this->xmlDoc);
        }
        else {
            return $this->content;
        }
    }

    //------------------------------------------------------
    // Page functionality
    //------------------------------------------------------
    
    function user() {
        if (isset($_SESSION["user"])) {
            $this->fillUser();
            if (isset($_SESSION["user_referer"]))
                unset($_SESSION["user_referer"]);
        }
        else {
        
            list($realHost,)=explode(':',$_SERVER['HTTP_HOST']);

            $cur_link = sprintf("https://%s/?page=%s&id=%d",
                $realHost,
                $_GET['page'],
                $_GET['id']
            );

            $_SESSION["user_referer"] = $cur_link;

            $vk_url = 'https://oauth.vk.com/authorize';
            $vk_params = array(
                'client_id'     => VK_CLIENT_ID,
                'redirect_uri'  => sprintf("https://%s/?page=oauth_vk", $realHost),
                'response_type' => 'code'
            );
            $vkLink = $vk_url . '?' . http_build_query($vk_params);

            $fb_url = "https://www.facebook.com/dialog/oauth";
            $fb_params = array(
                'client_id'     => FB_CLIENT_ID,
                'redirect_uri'  => sprintf("https://%s/?page=oauth_fb", $realHost),
                'response_type' => 'code'
            );
            $fbLink = $fb_url . '?' . http_build_query($fb_params);

            $gl_url = 'https://accounts.google.com/o/oauth2/auth';
            $gl_params = array(
                'client_id'     => GL_CLIENT_ID,
                'redirect_uri'  => sprintf("https://%s/?page=oauth_gl", $realHost),
                'response_type' => 'code',
                'scope'         => 'profile'
            );
            $glLink = $gl_url . '?' . http_build_query($gl_params);

            
            // login form

            $sNet = $this->xmlDoc->createElement("snetwork");
            $sNet = $this->eRoot->appendChild($sNet);
            $subNodeAttr = $this->xmlDoc->createAttribute('name');
            $subNodeAttr->value = 'Vkontakte'; 
            $sNet->appendChild($subNodeAttr);
            $sNetLink = $this->xmlDoc->createElement("link", htmlentities($vkLink));
            $sNet->appendChild($sNetLink);
            $sNetLogo = $this->xmlDoc->createElement("img", "LOGO_ADDR");
            $sNet->appendChild($sNetLogo);

            $sNet = $this->xmlDoc->createElement("snetwork");
            $sNet = $this->eRoot->appendChild($sNet);
            $subNodeAttr = $this->xmlDoc->createAttribute('name');
            $subNodeAttr->value = 'Facebook'; 
            $sNet->appendChild($subNodeAttr);
            $sNetLink = $this->xmlDoc->createElement("link", htmlentities($fbLink));
            $sNet->appendChild($sNetLink);
            $sNetLogo = $this->xmlDoc->createElement("img", "LOGO_ADDR");
            $sNet->appendChild($sNetLogo);

            $sNet = $this->xmlDoc->createElement("snetwork");
            $sNet = $this->eRoot->appendChild($sNet);
            $subNodeAttr = $this->xmlDoc->createAttribute('name');
            $subNodeAttr->value = 'Google'; 
            $sNet->appendChild($subNodeAttr);
            $sNetLink = $this->xmlDoc->createElement("link", htmlentities($glLink));
            $sNet->appendChild($sNetLink);
            $sNetLogo = $this->xmlDoc->createElement("img", "LOGO_ADDR");
            $sNet->appendChild($sNetLogo);

            $this->eRoot->appendChild($sNet);          
        }        
        
    }
    
    function commentsUnapprovedTotal() {
        $q = "SELECT count(*) AS total FROM comments WHERE approved IS NULL";
        $res = $this->hDbConn->query($q);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        
        $this->content = $row['total'];
    }
    
    /**
     * Makes an XML with unapproved comments:
     * 
     * <?xml version="1.0" encoding="utf-8"?>
     * <root>
     *  <comment id="COMMENT_ID" unit_id="UNIT_ID">
     *      COMMENT_TEXT
     *  </comment>
     *  <comment id="COMMENT_ID" unit_id="UNIT_ID">
     *      COMMENT_TEXT
     *  </comment>
     * </root>
     * 
     * @return void
     * 
     */
    function adminCommentsList() {
        if (isset($_POST['comment_id'])) {
            $stmt = $this->hDbConn->prepare('UPDATE comments SET approved=:approve WHERE id=:id');
            $stmt->bindParam(':approve', $approve, PDO::PARAM_STR);
            $stmt->bindParam(':id', $com_id, PDO::PARAM_INT);
            foreach ($_POST['comment_id'] as $com_id) {
                $approve = in_array($com_id, $_POST['approved']) ? 'TRUE' : 'FALSE';
                $stmt->execute();
            }
        }
        $q = "SELECT cm.id,cm.comment,un.id AS unit_id FROM comments cm ".
            "JOIN units un ON cm.unit_id=un.id ".
            "WHERE approved IS NULL ORDER BY cm.date ASC LIMIT 200";
        $res = $this->hDbConn->query($q);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $sComent = $this->xmlDoc->createElement("comment",
                htmlentities($row['comment']));
            $sComent = $this->eRoot->appendChild($sComent);
            $attr = $this->xmlDoc->createAttribute('id');
            $attr->value = $row['id'];
            $sComent->appendChild($attr);
            $attr = $this->xmlDoc->createAttribute('unit_id');
            $attr->value = $row['unit_id'];
            $sComent->appendChild($attr);
        }
    }
    
    function searchForm() {
        
        $xmlTop = $this->xmlDoc->createElement("page", $this->param1);
        $xmlTop = $this->eRoot->appendChild($xmlTop);

        $xmlTop = $this->xmlDoc->createElement("categories");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT cat.id,cat.name FROM categories cat ".
            "JOIN units u ON cat.id=u.cat_id ".
            "GROUP BY cat.id HAVING count(cat.id) > 0 ".
            "ORDER BY name";
        $res = $this->hDbConn->query($q);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("category",
                htmlentities($row['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $row['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (isset($_GET['vType']) && $_GET['vType']==$row['id']) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true';                 
                $xmlSubTop->appendChild($xmlSubTopAttr);
            }
            $xmlTop->appendChild($xmlSubTop);
        }
        $xmlTop = $this->xmlDoc->createElement("manufacturers");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT m.id,m.name FROM manufacturers m ".
            "JOIN units u ON m.id=u.manufacturer_id ".
            "GROUP BY m.id HAVING count(m.id)>0 ORDER BY name;";
        $res = $this->hDbConn->query($q);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("manufacturer",
                htmlentities($row['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $row['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (isset($_GET['vManuf']) && $_GET['vManuf']==$row['id']) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true';                 
                $xmlSubTop->appendChild($xmlSubTopAttr);
            }
            $xmlTop->appendChild($xmlSubTop);
        }
        $xmlTop = $this->xmlDoc->createElement("fdistricts");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT id,name FROM `fdistricts` ORDER BY name";
        $res = $this->hDbConn->query($q);
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("fdistrict",
                htmlentities($row['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $row['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (isset($_GET['vFedDistr']) && $_GET['vFedDistr']==$row['id']) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true';                 
                $xmlSubTop->appendChild($xmlSubTopAttr);
            }
            $xmlTop->appendChild($xmlSubTop);
        }
    
    }
    
    function title($page) {
        switch ($page) {
            case "page_search":
                $bFirst = true;
                if ($_GET['vType'] != 0) {
                    $stmt = $this->hDbConn->prepare('SELECT name FROM categories WHERE id=:id');
                    $stmt->bindValue(':id', $_GET['vType'], PDO::PARAM_INT);
                    $stmt->execute();
                    $this->content = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
                    $bFirst = false;
                }
                if ($_GET['vManuf']) {
                    $stmt = $this->hDbConn->prepare('SELECT name FROM manufacturers WHERE id=:id');
                    $stmt->bindValue(':id', $_GET['vManuf'], PDO::PARAM_INT);
                    $stmt->execute();
                    if (!$bFirst)
                        $this->content .= ' / ';
                    else
                        $bFirst = false;
                    $this->content .= $stmt->fetch(PDO::FETCH_ASSOC)['name'];
                }
                if ($_GET['vFedDistr']) {
                    $stmt = $this->hDbConn->prepare('SELECT name FROM fdistricts WHERE id=:id');
                    $stmt->bindValue(':id', $_GET['vFedDistr'], PDO::PARAM_INT);
                    $stmt->execute();
                    if (!$bFirst)
                        $this->content .= ' / ';
                    else
                        $bFirst = false;
                    $this->content .= $stmt->fetch(PDO::FETCH_ASSOC)['name'];
                }    
                if ($bFirst)
                    $this->content = "Агенство спецтехники Гусеница";
                break;
            case "page_unit":
                if ($_GET['id'] != 0) {
                    $q = sprintf("SELECT CONCAT(m.name,' ', u.name) as name ".
                        "FROM units u JOIN manufacturers m ON ".
                        "u.manufacturer_id=m.id WHERE u.id=%d",
                        $_GET['id']);
                    $res = $this->hDbConn->query($q);
                    $this->content = $res->fetch(PDO::FETCH_ASSOC)['name'];
                }
                break;
        }
    }

    /**
     * Generates XML for unit form content. Later we will combine it with XSL 
     * file to get empty form for unit adding or filled form for unit editing.
     * 
     * @return void
     */
    function unitForm() {        
        // is it a new unit or editing existing one
        if (isset($_GET['id'])) {
            $isEdit = true;
            
            $q = UNIT_QUERY.sprintf(" WHERE u.id=%d", $_GET['id']);
            $res = $this->hDbConn->query($q);
            $curUnit = $res->fetch(PDO::FETCH_ASSOC);
            
            $q = sprintf("SELECT img FROM images WHERE unit_id=%d", $_GET['id']);
            $imgRes = $this->hDbConn->query($q);

            $xmlTop = $this->xmlDoc->createElement("id", 
                htmlentities($curUnit['id']));
            $xmlTop = $this->eRoot->appendChild($xmlTop);
            
            $actType = "unit_edit";            
        }
        else {
            $isEdit = false;
            $actType = "unit_add";
        }
        
        $xmlTop = $this->xmlDoc->createElement("act", 
            $actType);
        $xmlTop = $this->eRoot->appendChild($xmlTop);

        $xmlTop = $this->xmlDoc->createElement("categories");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT id,name FROM categories ORDER BY name";
        $res = $this->hDbConn->query($q);
        while ($qrow = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("category",
                htmlentities($qrow['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $qrow['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (($isEdit) AND
                    ($curUnit['cat_id'] == $qrow['id'])) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true'; 
                $xmlSubTop->appendChild($xmlSubTopAttr);                
            }
            $xmlTop->appendChild($xmlSubTop);
        }
        $xmlTop = $this->xmlDoc->createElement("fdistricts");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT id,name FROM fdistricts ORDER BY name";
        $res = $this->hDbConn->query($q);
        while ($qrow = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("fdistrict",
                htmlentities($qrow['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $qrow['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (($isEdit) AND
                    ($curUnit['fd_id'] == $qrow['id'])) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true'; 
                $xmlSubTop->appendChild($xmlSubTopAttr);                
            }
            $xmlTop->appendChild($xmlSubTop);
        }
        $xmlTop = $this->xmlDoc->createElement("cities");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = sprintf("SELECT id,name FROM cities WHERE rd_id=%d",
            $curUnit['region_id']);
        $res = $this->hDbConn->query($q);
        while ($qrow = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("city",
                htmlentities($qrow['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $qrow['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (($isEdit) AND
                    ($curUnit['city_id'] == $qrow['id'])) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true'; 
                $xmlSubTop->appendChild($xmlSubTopAttr);                
            }
            $xmlTop->appendChild($xmlSubTop);
        }
        $xmlTop = $this->xmlDoc->createElement("manufacturers");
        $xmlTop = $this->eRoot->appendChild($xmlTop);
        $q = "SELECT id,name FROM manufacturers ORDER BY name";
        $res = $this->hDbConn->query($q);
        while ($qrow = $res->fetch(PDO::FETCH_ASSOC)) {
            $xmlSubTop = $this->xmlDoc->createElement("manufacturer",
                htmlentities($qrow['name']));
            $xmlSubTopAttr = $this->xmlDoc->createAttribute('id');
            $xmlSubTopAttr->value = $qrow['id']; 
            $xmlSubTop->appendChild($xmlSubTopAttr);
            if (($isEdit) AND
                    ($curUnit['manufacturer_id'] == $qrow['id'])) {
                $xmlSubTopAttr = $this->xmlDoc->createAttribute('selected');
                $xmlSubTopAttr->value = 'true'; 
                $xmlSubTop->appendChild($xmlSubTopAttr);                
            }
            $xmlTop->appendChild($xmlSubTop);
        }

        if ($isEdit) {
            $xmlTop = $this->xmlDoc->createElement("name", 
                htmlentities($curUnit['name']));
            $xmlTop = $this->eRoot->appendChild($xmlTop);
            $xmlTop = $this->xmlDoc->createElement("description", 
                htmlentities($curUnit['description']));
            $xmlTop = $this->eRoot->appendChild($xmlTop);
            $xmlTop = $this->xmlDoc->createElement("year", 
                htmlentities($curUnit['year']));
            $xmlTop = $this->eRoot->appendChild($xmlTop);
            $xmlTop = $this->xmlDoc->createElement("price", 
                htmlentities($curUnit['price']));
            $xmlTop = $this->eRoot->appendChild($xmlTop);
            if (isset($curUnit['mileage'])) {
                $xmlTop = $this->xmlDoc->createElement("mileage", 
                    $curUnit['mileage']);
                $xmlTop = $this->eRoot->appendChild($xmlTop);
            }
            if (isset($curUnit['op_time'])) {
                $xmlTop = $this->xmlDoc->createElement("op_time", 
                    $curUnit['op_time']);
                $xmlTop = $this->eRoot->appendChild($xmlTop);
            }
    
            $xmlTop = $this->xmlDoc->createElement("images");
            $xmlTop = $this->eRoot->appendChild($xmlTop);                
            while ($imgRow = $imgRes->fetch(PDO::FETCH_ASSOC)) {
                $xmlSubTop = $this->xmlDoc->createElement("img",
                    htmlentities($imgRow['img']));
                $xmlTop->appendChild($xmlSubTop);
            }
        }
    }

    /**
     * Generates XML data with unit comments for
     * specified unit page
     * 
     * <?xml version="1.0" encoding="utf-8"?>
     * <root>
     *  <unit_id>43</unit_id>
     *  <comments>
     *      <comment id="COMMENT_ID" user_id="USER_ID" 
     *                  type="COMMENT_TYPE" approved="IS_APPROVED">
     *          COMMENT_TEXT
     *          <comment id="COMMENT_ID" user_id="USER_ID"
     *                  type="COMMENT_TYPE" approved="IS_APPROVED">
     *              COMMENT_TEXT
     *          </comment>
     *      </comment>
     *  </comments>
     * </root>
     */
    function userComments() {
        // fill user login form
        if (isset($_SESSION["user"])) {
            $this->fillUser();
        }        
        
        $sUnitId = $this->xmlDoc->createElement("unit_id", $_GET['id']);
        $this->eRoot->appendChild($sUnitId);
        
        // fill comments list
        $q = "SELECT id,user_id,type,name,comment,approved FROM comments ".
            "WHERE unit_id=%d AND p_com_id IS NULL ORDER BY date ASC";
        $q = sprintf($q, $_GET['id']);
        $res = $this->hDbConn->query($q);
        if ($res->num_rows > 0) {
            $sComents = $this->xmlDoc->createElement("comments");
            $sComents = $this->eRoot->appendChild($sComents);
            
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $sComent = $this->xmlDoc->createElement("comment",
                    htmlentities($row['comment']));
                $sComent = $sComents->appendChild($sComent);
                $attr = $this->xmlDoc->createAttribute('name');
                $attr->value = $row['name'];
                $sComent->appendChild($attr);
                $attr = $this->xmlDoc->createAttribute('id');
                $attr->value = $row['id'];
                $sComent->appendChild($attr);
                $attr = $this->xmlDoc->createAttribute('user_id');
                $attr->value = $row['user_id'];
                $sComent->appendChild($attr);
                $attr = $this->xmlDoc->createAttribute('type');
                $attr->value = $row['type'];
                $sComent->appendChild($attr);
                $attr = $this->xmlDoc->createAttribute('approved');
                if (!isset($row['approved']) OR $row['approved'])
                    $attr->value = 'true';
                else
                    $attr->value = 'false';
                $sComent->appendChild($attr);

                $q = "SELECT id,user_id,type,name,comment,approved FROM comments ".
                    "WHERE p_com_id=%d ORDER BY date ASC";
                $q = sprintf($q, $row['id']);
                $subRes = $this->hDbConn->query($q);
                while ($subRow = $subRes->fetch(PDO::FETCH_ASSOC)) {
                    $sSubComent = $this->xmlDoc->createElement("comment",
                        htmlentities($subRow['comment']));
                    $sSubComent = $sComent->appendChild($sSubComent);
                    $attr = $this->xmlDoc->createAttribute('name');
                    $attr->value = $subRow['name'];
                    $sSubComent->appendChild($attr);
                    $attr = $this->xmlDoc->createAttribute('id');
                    $attr->value = $subRow['id'];
                    $sSubComent->appendChild($attr);
                    $attr = $this->xmlDoc->createAttribute('user_id');
                    $attr->value = $subRow['user_id'];
                    $sSubComent->appendChild($attr);
                    $attr = $this->xmlDoc->createAttribute('type');
                    $attr->value = $subRow['type'];
                    $sSubComent->appendChild($attr);
                    $attr = $this->xmlDoc->createAttribute('approved');
                    if (!isset($subRow['approved']) OR $subRow['approved'])
                        $attr->value = 'true';
                    else
                        $attr->value = 'false';
                    $sSubComent->appendChild($attr);
                }

            }
        }
//echo $this->xmlDoc->saveXML();
//exit;
        
    }

    function searchPaginator($page) {
        $q = <<<EOT
SELECT
COUNT(*) AS total
FROM units
JOIN cities ON units.city_id=cities.id
JOIN regions ON cities.rd_id=regions.id
JOIN fdistricts ON regions.fd_id=fdistricts.id
JOIN categories ON units.cat_id=categories.id
JOIN manufacturers ON manufacturers.id=units.manufacturer_id
WHERE is_arch=FALSE
EOT;

        $bNeedAND = false;
        if ($_GET['vType'] != 0) {
            $q .= sprintf(" AND cat_id=%d", $_GET['vType']);
        }
        if ($_GET['vManuf']) {
            $q .= sprintf(" AND manufacturer_id=%d", $_GET[vManuf]);
        }
        if ($_GET['vFedDistr']) {
            $q .= sprintf(" AND fd_id=%d", $_GET[vFedDistr]);
        }

        $countRes = $this->hDbConn->query($q);
        $iTotal = $countRes->fetch(PDO::FETCH_ASSOC)['total'];

        // current page number
        if (isset($_GET['offset'])) {
            $iOffset = max($_GET['offset'], 1);
        } else {
            $iOffset = 1;
        }
        
        $vType = isset($_GET['vType']) ? $_GET['vType'] : 0;
        $vManuf = isset($_GET['vManuf']) ? $_GET['vManuf'] : 0;
        $vFedDistr = isset($_GET['vFedDistr']) ? $_GET['vFedDistr'] : 0;
        switch ($page) {
            case "search":
                $sLinkPattern = htmlentities("/search/$vType/$vManuf/$vFedDistr/%d");
                break;
            case "admin":
                $sLinkPattern = htmlentities("?page=admin&act=main&vType=".
                    $vType."&vManuf=".$vManuf."&vFedDistr=".
                    $vFedDistr."&offset=%d"
                );
                break;
        }

        $this->paginator(
            $sLinkPattern,
            $iTotal,
            PAGINATOR_SHOW_ON_PAGE,
            $iOffset,
            PAGINATOR_PAGES_IN_NAV
        );        
    }
    
    function searchPageMain() {
        $q = UNIT_QUERY . " WHERE is_arch=FALSE";

        if ($_GET['vType'] != 0) {
            $q .= sprintf(" AND cat_id=%d", $_GET['vType']);
        }
        if ($_GET['vManuf']) {
            $q .= sprintf(" AND manufacturer_id=%d", $_GET[vManuf]);
        }
        if ($_GET['vFedDistr']) {
            $q .= sprintf(" AND fd_id=%d", $_GET[vFedDistr]);
        }

        if (isset($_GET['offset'])) {
            $iOffset = max($_GET['offset'], 1);
        } else {
            $iOffset = 1;
        }        
        
        $q .= sprintf(" ORDER BY u.date DESC LIMIT %d,%d",
            ($iOffset-1)*PAGINATOR_SHOW_ON_PAGE,
            PAGINATOR_SHOW_ON_PAGE
        );

        $res = $this->hDbConn->query($q);
        while ($ur = $res->fetch(PDO::FETCH_ASSOC)) {
            $this->fillUnit($ur);
        }
    }

    function unitPageMain() {
        $q = UNIT_QUERY." WHERE u.id=%d";

        $q = sprintf($q, $_GET['id']);
        $res = $this->hDbConn->query($q);
        $ur = $res->fetch(PDO::FETCH_ASSOC);
        $eUnit = $this->fillUnit($ur);

        $q = "SELECT img FROM images WHERE images.unit_id=%d ORDER BY `order`";

        $q = sprintf($q, $_GET['id']);
        $res = $this->hDbConn->query($q);
        while ($ir = $res->fetch(PDO::FETCH_ASSOC)) {
            $eImages = $this->xmlDoc->createElement('images');
            $eImages = $eUnit->appendChild($eImages);
            $eImg = $this->xmlDoc->createElement('img', htmlentities($ir['img']));
            $eImages->appendChild($eImg);
        }
    }
    
    function mainPageList() {
        $q = "SELECT cat.id, cat.name FROM categories cat JOIN units u ".
            "ON cat.id=u.cat_id GROUP By cat.id HAVING count(cat.id)>0";
        $cat_res = $this->hDbConn->query($q);
        while ($cr = $cat_res->fetch(PDO::FETCH_ASSOC)) {
            $eCat = $this->xmlDoc->createElement('category');
            $eCatId = $this->xmlDoc->createAttribute('id');
            $eCatId->value = htmlentities($cr['id']);
            $eCat->appendChild($eCatId);              
            $eCatName = $this->xmlDoc->createAttribute('name');
            $eCatName->value = htmlentities($cr['name']);
            $eCat->appendChild($eCatName);              
            $eCat = $this->eRoot->appendChild($eCat);
            $q = UNIT_QUERY." WHERE is_arch=FALSE AND cat_id=%d ORDER BY u.date DESC LIMIT 4";

            $q = sprintf($q, $cr['id']);
            $unit_res = $this->hDbConn->query($q);
            while ($ur = $unit_res->fetch(PDO::FETCH_ASSOC)) {
                $eCat->appendChild($this->fillUnit($ur));
                
            }
        }
    }
        
}

?>