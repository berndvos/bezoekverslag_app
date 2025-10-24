<?php

namespace App\Core;

use App\Controllers\BezoekverslagController;
use App\Controllers\RuimteController;
use App\Controllers\UploadController;

class Router {
    public static function route($default = 'dashboard') {
        $page = $_GET['page'] ?? $default;
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
