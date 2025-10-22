<?php
class Router {
    public static function route($default = 'dashboard') {
        $page = $_GET['page'] ?? $default;
        require_once __DIR__ . '/../app/controllers/BezoekverslagController.php';
        require_once __DIR__ . '/../app/controllers/RuimteController.php';
        require_once __DIR__ . '/../app/controllers/UploadController.php';
        $controller = new BezoekverslagController();
        switch ($page) {
            case 'dashboard': $controller->index(); break;
            case 'nieuw': $controller->create(); break;
            case 'bewerk': $controller->edit($_GET['id'] ?? null); break;
            case 'submit': $controller->submit($_GET['id'] ?? null); break;
            case 'ruimte': (new RuimteController())->edit($_GET['id'] ?? null, $_GET['verslag'] ?? null); break;
            case 'ruimte_new': (new RuimteController())->create($_GET['verslag'] ?? null); break;
            case 'upload': (new UploadController())->uploadFile(); break;
            default: $controller->index();
        }
    }
}
