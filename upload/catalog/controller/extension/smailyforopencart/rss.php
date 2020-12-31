<?php

class ControllerExtensionSmailyForOpencartRss extends Controller {

    public function index() {
        // Load models.
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('localisation/currency');

        $category = null;
        $limit = 50;
        $sort = 'p.date_added';
        $order = 'DESC';
        // Get params from URL for filtering products.
        if (isset($this->request->get['category'])) {
            $category = $this->request->get['category'];
            // Category parameter can be either in string format or category ID.
            $category = (int) $category > 0 ? (int) $category : $this->getCategoryIdByName($category);
        }
        if (isset($this->request->get['limit'])) {
            $limit = (int) $this->request->get['limit'];
            $limit = $limit >= 1 && $limit < 250 ? $limit : 50;
        }
        if (isset($this->request->get['sort_by'])) {
            $sort = $this->request->get['sort_by'];
            $sort = in_array($sort, array('pd.name', 'p.model', 'p.price', 'p.status', 'p.sort_order'), true) ? $sort : 'p.date_added';
        }
        if (isset($this->request->get['sort_order'])) {
            $order = $this->request->get['sort_order'];
            $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';
        }

        // Xml header.
        $this->response->addHeader('Content-Type: text/xml; charset=utf-8');
        // Store url.
        if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
            $data['store_url'] = $this->config->get('config_ssl');
        } else {
            $data['store_url'] = $this->config->get('config_url');
        }
        // Build date.
        $data['last_build_date'] = date('D, d M Y H:i:s');
        // Currency symbol.
        $data['currency'] = $this->session->data['currency'];
        // Filter for query.
        $filter = array(
            'sort' => $sort,
            'order' => $order,
            'start' => 0,
            'limit' => $limit
        );
        // Add category if set.
        if ($category) {
            $filter['filter_category_id'] = $category;
            $filter['filter_sub_category'] = true;
        }
        // Get items based on filer.
        $products = $this->model_catalog_product->getProducts($filter);
        // Items array for template.
        $items = [];
        foreach ($products as $product) {
            $item = [];
            // Title.
            $item['title'] = html_entity_decode($product['name']);
            // Link, guid
            $item['link'] = $this->url->link('product/product', 'product_id=' . $product['product_id']);
            // Created date.
            $item['pubDate'] = $product['date_available'];
            // Description.
            $item['description'] = html_entity_decode($product['description']);
            // Enclosure.
            $item['enclosure'] = $this->model_tool_image->resize($product['image'], 300, 300);
            // Price.
            $item['price'] = round($product['price'], 2);
            // Check if product is on sale.
            if (!empty($product['special']) && $product['special'] > 0) {
                $item['price'] = round($product['special'], 2);
                $item['old_price'] = round($product['price'], 2);
                $item['discount'] = round(100 - ($product['special'] / $product['price'] * 100), 2);
            }
            $items[] = $item;
        }
        $data['items'] = $items;
        // Return output.
        $this->response->setOutput($this->load->view('extension/smailyforopencart/rss', $data));
    }

    /**
     * Returns category ID based on category name. Name must be url-encoded.
     * When no category found with that name, returns empty string.
     *
     * @param string $name Category name
     * @return string $id  Category id.
     */
    public function getCategoryIdByName($name) {
        $this->load->model('catalog/category');
        $id = '';
        // All categories.
        $categories = $this->model_catalog_category->getCategories();
        foreach ($categories as $category) {
            if ($category['name'] == $name) {
                // Get id if name matches.
                $id = $category['category_id'];
            }
        }
        return $id;
    }
}
