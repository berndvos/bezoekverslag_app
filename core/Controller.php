<?php
class Controller {
    protected function view($path, $vars = []) {
        extract($vars);
        include __DIR__ . '/../app/views/layout/header.php';
        include __DIR__ . '/../app/views/' . $path . '.php';
        include __DIR__ . '/../app/views/layout/footer.php';
    }
}
