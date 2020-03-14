<?php
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../lib/vendors/Inflector.php';
require_once '../lib/database.php';
ini_set('error_reporting', E_ALL);
global $_server_domain_url;
$inflector = new Inflector();
$php_path = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
$api_url_map = array(     
    '/\/user\/(?P<user_id>\d+)\/(?P<slug>.*)/' => array(
        'api_url' => '/api/v1/users/{id}',
    ) ,
    '/\/users(.*)/' => array(
        'api_url' => '/api/v1/users',
        'title' => 'Users'
    ) ,
    '/^\/users\/login$/' => array(
        'api_url' => null,
        'title' => 'Login'
    ) ,
    '/^\/users\/register$/' => array(
        'api_url' => null,
        'title' => 'Register'
    ) ,
    '/^\/users\/forgot_password$/' => array(
        'api_url' => null,
        'title' => 'Forgot Password'
    ) ,
    '/\/page\/(?P<page_id>\d+)\/(?P<slug>.*)/' => array(
        'api_url' => '/api/v1/pages/{id}',
    ) ,
    '/^\/$/' => array(
        'api_url' => null,
        'title' => 'Home'
    ) ,
);
$meta_keywords = $meta_description = $title = $site_name = '';
$og_image = $_server_domain_url . '/images/no_image_available.png';
$og_type = 'website';
$og_url = $_server_domain_url . '' . $_GET['_escaped_fragment_'];
$res = Models\Setting::whereIn('name', array(
    'META_KEYWORDS',
    'META_DESCRIPTION',
    'SITE_NAME'
))->get()->toArray();
foreach ($res as $key => $arr) {
    if ($res[$key]['name'] == 'META_KEYWORDS') {
        $meta_keywords = $res[$key]['value'];
    }
    if ($res[$key]['name'] == 'META_DESCRIPTION') {
        $meta_description = $res[$key]['value'];
    }
    if ($res[$key]['name'] == 'SITE_NAME') {
        $title = $site_name = $res[$key]['value'];
    }
}
if (!empty($_GET['_escaped_fragment_'])) {
    foreach ($api_url_map as $url_pattern => $values) { 
        if (preg_match($url_pattern, $_GET['_escaped_fragment_'], $matches)) {
             // Match _escaped_fragment_ with our api_url_map array; For selecting API call
            if (!empty($values['business_name'])) { //Default title; We will change title for course and user page below;
                $title = $site_name . ' | ' . $values['business_name'];
            }  
            if (!empty($values['api_url'])) {
                $id = (!empty($matches['page_id']) ? $matches['page_id'] : '');
                if (!empty($id)) {
                    $api_url = str_replace('{id}', $id, $values['api_url']); // replacing id value
                } else {
                    $api_url = $values['api_url']; // using defined api_url
                }
               $query_string = !empty($matches[1]) ? $matches[1] : '';
               $response = json_decode(shell_exec($php_path . " index.php " . $api_url . " GET " . $query_string), true); 
                if (!empty($response['data'])) {
                    foreach ($response['data'] as $key => $value) {
                        if ($values['api_url'] == '/api/v1/pages/{id}') {
                            if ($key == 'meta_keywords') {
                                $meta_keywords = !empty($value) ? $value : '';
                            }
                            if ($key == 'meta_description') {
                                $meta_description = !empty($value) ? $value : '';
                            }
                        } 
                        elseif ($values['api_url'] == '/api/v1/quote_services/{id}') {
                                $og_type = 'LocalBusiness';
                                $api_url = '/api/v1/quote_services/{id}';
                            if ($key == 'id') {
                                $quote_services_id = $value;
                            }    
                            if ($key == 'business_name') {
                                $meta_keywords = !empty($value) ? $value : '';
                                $title =  !empty($value) ? $value: $title;
                                $business_name =  !empty($value) ? $value: $title;
                            }
                            if ($key == 'phone_number') {
                                $contact['@type'] = 'ContactPoint';
                                $contact['contactType'] = 'mobile';
                                $contact['telephone'] =  $value;
                            }
                            $location ['@type'] = 'Place';
                            $offer['@type'] = 'Offer';
                            $geoloc['@type'] = 'GeoCoordinates';
                            $location['address']['@type'] = 'PostalAddress';
                            if ($key == 'city') {
                                $location['address']['streetAddress'] =  $value['name'];
                            }
                            if ($key == 'state') {
                                $location['address']['addressRegion'] =  $value['name'];
                            }
                            if ($key == 'country') {
                                $location['address']['addressCountry'] =  $value['name'];
                            }
                            if ($key == 'zip_code'){
                                $location['address']['postalCode'] =  $value;
                            }
                            if ($key == 'latitude'){
                                $geoloc['latitude'] =  $value;
                            }
                            if ($key == 'longitude'){
                                $geoloc['longitude'] =  $value;
                            }                            
                            if ($key == 'attachment' && !empty($value)) {
                                $og_image = $_server_domain_url . '/images/large_thumb/QuoteService/' . $quote_services_id . '.' . md5('QuoteService' . $quote_services_id . 'png' . 'large_thumb') . '.' . 'png';
                            }
                            if ($key == 'slug' && $value != NULL) {
                                $og_url = $_server_domain_url . '/quote_service/' . $quote_services_id . '/' . $value;
                            }
                        }
                    }
                } else {
                    $isNoRecordFound = 1;
                }
            }
            break;
        } 
    }
}
if (!empty($response->error) || !empty($isNoRecordFound) || empty($matches)) { // returning 404, if URL or record not found
    header('Access-Control-Allow-Origin: *');
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
    exit;
}
$app_id = Models\Provider::where('name', 'Facebook')->first();
?>
<!DOCTYPE html>
<html>
<head>
 <title><?php echo $title; ?></title>
  <meta charset="UTF-8">
  <meta name="description" content="<?php
    echo $meta_description; ?>"/>
  <meta name="keywords" content="<?php
    echo $meta_keywords; ?>"/>
  <meta property="og:app_id" content="<?php
    echo $app_id->api_key; ?>"/>
  <meta property="og:type" content="<?php
    echo $og_type; ?>"/>
  <meta property="og:title" content="<?php
    echo $title; ?>"/>
  <meta property="og:description" content="<?php
    echo $meta_description; ?>"/>
  <meta property="og:type" content="<?php
    echo $og_type; ?>"/>
  <meta property="og:image" content="<?php
    echo $og_image; ?>"/>
  <meta property="og:site_name" content="<?php
    echo $site_name; ?>"/>
  <meta property="og:url" content="<?php
    echo $og_url; ?>"/> 
    <?php
        if ($api_url == '/api/v1/jobs/{id}'){
            $datas['@context'] = "http://www.schema.org";
            $datas['@type'] =  $og_type;
            $datas['image'] = $og_image;
            $datas['description'] = $meta_description;
            $datas['name'] = $meta_keywords;
            $datas['url'] = $og_url;
            $datas['location'] = $location;
            $datas['offers'] = $offer;
        }
        if ($api_url == '/api/v1/projects/{id}'){
            $datas['@context'] = "http://www.schema.org";
            $datas['@type'] =  $og_type;
            $datas['name'] = $project_name;
            $datas['image'] = $og_image;
            $datas['description'] = $meta_description;
            $datas['aggregateRating'] = $rating;
        }
        if ($api_url == '/api/v1/contests/{id}'){
            $datas['@context'] = "http://www.schema.org";
            $datas['@type'] =  $og_type;
            $datas['name'] = $contest_name;
            $datas['image'] = $og_image;
            $datas['url'] = $og_url;
            $datas['description'] = $meta_description;
            $datas['offers'] = $offer;
        }
        if ($api_url == '/api/v1/portfolios/{id}'){
            $datas['@context'] = "http://www.schema.org";
            $datas['@type'] =  $og_type;
            $datas['name'] = $portfolios_name;
            $datas['image'] = $og_image;
            $datas['url'] = $og_url;
        }
        if ($api_url == '/api/v1/quote_services/{id}'){
            $datas['@context'] = "http://www.schema.org";
            $datas['@type'] =  $og_type;
            $datas['name'] = $business_name;
            $datas['image'] = $og_image;
            $datas['url'] = $og_url;
            $datas['description'] = $meta_description;
            $datas['address'] = $location;
            $datas['geo'] = $geoloc;
            $datas['contactPoint'] = $contact;
        }
   ?>
   <script type = "application/ld+json">
        <?php
           echo json_encode($datas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        ?>
    </script>
</head>
<body>
</body>
</html>