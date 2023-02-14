public function searchPinterest($q)
{
    $url = "https://pinterest.com/search/pins/?q=$q";

    $pinterestPage = file_get_contents($url);

    $domDoc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $domDoc->loadHTML($html);
    libxml_use_internal_errors(false);

    $items = $domDoc->getElementsByTagName('script');
    $data = array();

    foreach ($items as $item) {
        $data[] = [
            'src' => $item->getAttribute('src'),
            'outerHTML' => $domDoc->saveHTML($item),
            'innerHTML' => $domDoc->saveHTML($item->firstChild),
        ];
    }

    foreach ($data as $key => $value) {
        $response = json_decode($value['innerHTML']);
        if (!$response) {
            continue;
        }
        if (isset($response->tree->data->results)) {
            foreach ($response->tree->data->results as $obj) {
                print_r($obj->like_count);
                $images = (Array) $obj->images;
                print_r($images['736x']->url);

            }
        }
    }
}
