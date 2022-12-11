<?php

namespace xml_map_generator;

use Category, Context, Link, Product;
use CMSCore;
use Faker\Provider\Image;
use SimpleXMLElement;

include '../autoload.php';
include '../config/config.inc.php';

ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');

final class Sitemap_generator
{
    private $base_url;
    private $categories_urls;
    private $products_urls;
    private $images_urls;
    private $cms_urls;
    private $id_lang;
    private $context;
    private $link;
    private $categories;


    /*
    *   Construct
    *
    */
    public function __construct()
    {
        $context = Context::getContext();
        $link = new Link();
        $this->context = $context;
        $this->link = $link;
        $this->base_url = $link->getBaseLink();
        $this->id_lang = $context->language->id;
        $this->main();
    }


    /*
    *   Main core
    *
    */
    private function main() : void
    {
        //category builder
        $this->get_all_categories();
        $this->generate_xml(
            $this->categories_urls,
            0.5,
            'category'
        );

        //products bulder
        $this->get_all_products();
        $this->generate_xml(
            $this->products_urls,
            1.0,
            'products'
        );

        //imgs builder
        $this->get_all_images($this->products_urls);
        $this->generate_xml($this->images_urls,
            0.5,
            'images`'
        );

        //imgs builder
        $this->create_cms_pages();
        $this->generate_xml(
            $this->cms_urls,
            0.5,
            'cms'
        );

    }


    /*
    *   Creating categories links.
    *   Return reference 'categories_urls'.
    */
    private function get_all_categories() : void
    {
        $this->categories = Category::getAllCategoriesName(
            null,
            $this->context->language->id,
            true
        );
        $categories_urls = [];
        foreach ($this->categories as $category) {
            $category_item = [
                'name' => $category['name'],
                'id' =>   $category['id_category'],
                'url' =>  $this->link->getCategoryLink((int)$category['id_category'])
            ];
            array_push($categories_urls, $category_item);
        }
        $this->categories_urls = $categories_urls;
    }


    /*
    *   Creating products links.
    *   Return reference 'categories_urls'.
    */
    private function get_all_products() : void
    {
        $products = Product::getProducts(
            $this->id_lang,
            0,
            NULL,
            'id_product',
            'ASC',
            false,
            true,
            $this->context
        );
        $products_urls = [];
        foreach ($products as $product) {
            $product_object = new Product((int)$product['id_product']);
            $product_item = [
                'name' => $product['name'],
                'id' =>   $product['id_product'],
                'url' =>  $this->link->getProductLink($product_object)
            ];
            array_push($products_urls, $product_item);
        }
        $this->products_urls = $products_urls;
    }


    /*
    *   Creating images links.
    *   Return reference 'images_urls'.
    */
    private function get_all_images(array $product_list) : void
    {
        $images_urls = [];
        foreach ($product_list as $item) {
            $product = new Product(
                $item['id'],
                null,
                $this->context->language->id,
                $this->context->shop->id,
                $this->context
            );
            $images = $product->getImages(
                $item['id'],
                $this->context
            );
            foreach ($images as $img) {
                $image['url'] = $this->context->link->getImageLink(
                    $product->link_rewrite,
                    $img['id_image']
                );
                $image_item = [
                    'name' => $product->name,
                    'id' =>   $item['id'],
                    'url' =>  $image['url']
                ];
                array_push($images_urls, $image_item);
            }
        }
        $this->images_urls = $images_urls;
    }


    /*
    *   Creating CMS links.
    *   Return reference 'categories_urls'.
    */
    public function create_cms_pages() : void
    {
        $cms_urls = [];
        $cms_object = new CMSCore(
            null,
            $this->context->language->id,
            $this->context->shop->id
        );
        $cms_group = $cms_object::getLinks(
            $this->context->language->id,
            null,
            true
        );

        foreach($cms_group as $item){
            $cms_item = [
                'name' => $item['meta_title'],
                'id' =>   $item['id'],
                'url' =>  $item['link']
            ];
            array_push($cms_urls, $cms_item);
        }
        $this->cms_urls = $cms_urls;
    }


    /*
    * Checking link code.
    * Return true if site code is 200.
    */
    private function check_urls(array $url) : bool
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $data = curl_exec($curl);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);
        var_dump($curl_info);
        if ($curl_info['http_code'] === 200) {
            return true;
        } else {
            return false;
        }
    }


    /*
    *   Generate xml file.
    *
    */
    private function generate_xml(array $list, float $prority, string $filename) : void
    {
        $xml = new SimpleXMLElement('<xml/>');
        Header('Content-type: text/xml');
        foreach ($list as $element) {
            $item = $xml->addChild('item');
            $url = $item->addChild('url', $element['url']);
            $priority = $item->addChild('priority', $prority);
        }
        $xml->asXML('./'.$filename.'.xml');
    }
}

$s_gen = new Sitemap_generator();