<?php
class Controller {
    protected function view($path, $vars = []) {
        extract($vars);
        include_once __DIR__ . '/../app/views/layout/header.php';
        include_once __DIR__ . '/../app/views/' . $path . '.php';
        include_once __DIR__ . '/../app/views/layout/footer.php';
    }
}
