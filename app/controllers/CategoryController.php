<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

require_once ROOT_DIR . '/app/models/Category.php';
require_once ROOT_DIR . '/app/models/User.php';

class CategoryController {
    private $conn;
    private $categoryModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->categoryModel = new Category($db);
        $this->userModel = new User($db);
    }

    public function manageCategories() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->redirect('home.php');
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['categoryError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("admin_dashboard.php?limit=$limit&offset=$offset");
            }

            if (isset($_POST['create'])) {
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                if (empty($name) || strlen($name) > 100) {
                    $_SESSION['categoryError'] = "Tên danh mục không được để trống và tối đa 100 ký tự.";
                } elseif (strlen($description) > 65535) { // TEXT column max length
                    $_SESSION['categoryError'] = "Mô tả quá dài.";
                } else {
                    $result = $this->categoryModel->create($name, $description);
                    if ($result['success']) {
                        $this->userModel->logAction(
                            $this->userModel->getCurrentAdminId(),
                            'create_category',
                            null,
                            "Đã tạo danh mục ID {$result['category_id']} với tên '$name'"
                        );
                        $_SESSION['categorySuccess'] = $result['message'];
                    } else {
                        $_SESSION['categoryError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['update'])) {
                $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $status = isset($_POST['status']) ? 1 : 0;
                if (!$category_id || empty($name) || strlen($name) > 100) {
                    $_SESSION['categoryError'] = "Dữ liệu không hợp lệ hoặc tên vượt quá 100 ký tự.";
                } elseif (strlen($description) > 65535) {
                    $_SESSION['categoryError'] = "Mô tả quá dài.";
                } else {
                    $result = $this->categoryModel->update($category_id, $name, $description, $status);
                    if ($result['success']) {
                        $this->userModel->logAction(
                            $this->userModel->getCurrentAdminId(),
                            'update_category',
                            null,
                            "Đã cập nhật danh mục ID $category_id với tên '$name'"
                        );
                        $_SESSION['categorySuccess'] = $result['message'];
                    } else {
                        $_SESSION['categoryError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
                if (!$category_id) {
                    $_SESSION['categoryError'] = "ID danh mục không hợp lệ.";
                } else {
                    $result = $this->categoryModel->delete($category_id);
                    if ($result['success']) {
                        $this->userModel->logAction(
                            $this->userModel->getCurrentAdminId(),
                            'delete_category',
                            null,
                            "Đã xóa danh mục ID $category_id"
                        );
                        $_SESSION['categorySuccess'] = $result['message'];
                    } else {
                        $_SESSION['categoryError'] = $result['message'];
                    }
                }
            }
            $this->redirect("admin_dashboard.php?limit=$limit&offset=$offset");
        }

        return $this->categoryModel->getAll($limit, $offset);
    }

    public function getCategories() {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        // Chỉ trả về danh mục hoạt động nếu không phải admin
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return $this->categoryModel->getActive($limit, $offset);
        }
        return $this->categoryModel->getAll($limit, $offset);
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}