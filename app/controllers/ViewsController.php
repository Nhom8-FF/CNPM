<?php
/**
 * ViewsController - Controller for handling direct view access
 * 
 * This controller handles URLs that directly access views, avoiding 404 errors
 */

require_once __DIR__ . '/AppController.php';

class ViewsController extends AppController {
    
    /**
     * Default action - load the product view based on URL
     */
    public function index() {
        // Get the product path from URL 
        $urlParts = explode('/', trim($_GET['url'] ?? '', '/'));
        
        // Skip 'views' and 'product' if present
        if (!empty($urlParts) && $urlParts[0] === 'views') {
            array_shift($urlParts);
        }
        if (!empty($urlParts) && $urlParts[0] === 'product') {
            array_shift($urlParts);
        }
        
        // Build the view path, default to home if empty
        $viewPath = !empty($urlParts) ? implode('/', $urlParts) : 'home';
        
        // Remove .php extension if present (to avoid double extension)
        $viewPath = str_replace('.php', '', $viewPath);
        
        // Render the view file directly
        $this->render('product/' . $viewPath);
    }
    
    /**
     * Handle 'product' action - load a product view
     */
    public function product() {
        $urlParts = explode('/', trim($_GET['url'] ?? '', '/'));
        
        // Skip to the parts after 'product'
        $foundProduct = false;
        foreach ($urlParts as $index => $part) {
            if ($part === 'product') {
                $foundProduct = true;
                unset($urlParts[$index]);
                break;
            }
            unset($urlParts[$index]);
        }
        
        // Build the view path, adding 'product/' prefix
        $urlParts = array_values($urlParts); // Re-index array
        $viewPath = !empty($urlParts) ? implode('/', $urlParts) : 'home';
        
        // Remove .php extension if present (to avoid double extension)
        $viewPath = str_replace('.php', '', $viewPath);
        
        // Render the view
        $this->render('product/' . $viewPath);
    }
} 