<?php
/**
 * ProKB API Router
 * Главный файл для обработки всех API запросов
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// CORS
setCorsHeaders();

// Маршрутизация
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$path = $_GET['path'] ?? '';

// Определяем маршрут
$routes = [
    // Аутентификация
    'POST /auth/login' => 'authLogin',
    'POST /auth/logout' => 'authLogout',
    'GET /auth/me' => 'authMe',
    
    // Проекты
    'GET /projects' => 'getProjects',
    'GET /projects/{id}' => 'getProject',
    'POST /projects' => 'createProject',
    'PUT /projects/{id}' => 'updateProject',
    'DELETE /projects/{id}' => 'deleteProject',
    
    // Разделы
    'GET /sections/{id}' => 'getSection',
    'PUT /sections/{id}/status' => 'updateSectionStatus',
    'PUT /sections/{id}/assignee' => 'assignSection',
    'POST /sections/{id}/files' => 'uploadSectionFile',
    'DELETE /sections/{id}/files/{fileId}' => 'deleteSectionFile',
    'POST /sections/{id}/complete' => 'uploadCompletedSection',
    'DELETE /sections/{id}/complete' => 'deleteCompletedSection',
    
    // Изыскания
    'GET /investigations/standard' => 'getStandardInvestigations',
    'GET /projects/{id}/investigations' => 'getProjectInvestigations',
    'POST /projects/{id}/investigations' => 'addInvestigation',
    'PUT /investigations/{id}' => 'updateInvestigation',
    'DELETE /investigations/{id}' => 'deleteInvestigation',
    
    // Сотрудники
    'GET /employees' => 'getEmployees',
    'GET /employees/{id}' => 'getEmployee',
    'POST /employees' => 'createEmployee',
    'PUT /employees/{id}' => 'updateEmployee',
    'DELETE /employees/{id}' => 'deleteEmployee',
    
    // Задачи
    'GET /tasks' => 'getTasks',
    'GET /tasks/{id}' => 'getTask',
    'POST /tasks' => 'createTask',
    'PUT /tasks/{id}' => 'updateTask',
    'DELETE /tasks/{id}' => 'deleteTask',
    
    // Контакты
    'GET /projects/{id}/contacts' => 'getContacts',
    'POST /projects/{id}/contacts' => 'addContact',
    'DELETE /contacts/{id}' => 'deleteContact',
    
    // Вводная информация
    'GET /projects/{id}/intro' => 'getIntroBlocks',
    'POST /projects/{id}/intro' => 'addIntroBlock',
    'DELETE /intro/{id}' => 'deleteIntroBlock',
    
    // Стандартные изыскания (админка)
    'GET /admin/investigations' => 'adminGetInvestigations',
    'POST /admin/investigations' => 'adminAddInvestigation',
    'DELETE /admin/investigations/{id}' => 'adminDeleteInvestigation',
    
    // Разделы проектирования (админка)
    'GET /admin/sections' => 'adminGetSections',
    'POST /admin/sections' => 'adminAddSection',
    'DELETE /admin/sections/{id}' => 'adminDeleteSection',
];

// Найти匹配ающий маршрут
$matched = false;
$requestPath = $method . ' /' . ($action ? $action . '/' . $path : $path);

foreach ($routes as $route => $handler) {
    // Простое сопоставление (нужно улучшить для поддержки {id})
    if (strpos($requestPath, $route) !== false || matchRoute($route, $requestPath)) {
        $matched = true;
        call_user_func($handler);
        break;
    }
}

if (!$matched) {
    sendError('Маршрут не найден: ' . $requestPath, 404);
}

// ============================================
// Функции-обработчики
// ============================================

/**
 * Сопоставление маршрута с параметрами
 */
function matchRoute($pattern, $path) {
    $pattern = str_replace('{id}', '(\d+)', $pattern);
    $pattern = str_replace('{fileId}', '(\d+)', $pattern);
    return preg_match('#^' . $pattern . '$#', $path);
}

/**
 * Извлечение ID из пути
 */
function getIdFromPath($path, $position = 1) {
    preg_match('/(\d+)/', $path, $matches);
    return isset($matches[$position]) ? (int)$matches[$position] : 0;
}

// ============================================
// АВТОРИФИКАЦИЯ
// ============================================

function authLogin() {
    $data = getJsonInput();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendError('Email и пароль обязательны');
    }
    
    $user = getUserByEmail($email);
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        sendError('Неверный email или пароль');
    }
    
    // Создаём сессию
    $token = generateToken();
    $userId = $user['id'];
    
    dbQuery("UPDATE users SET token = ? WHERE id = ?", [$token, $userId]);
    
    unset($user['password']);
    
    sendSuccess([
        'user' => $user,
        'token' => $token
    ]);
}

function authLogout() {
    $userId = getCurrentUserId();
    if ($userId) {
        dbQuery("UPDATE users SET token = NULL WHERE id = ?", [$userId]);
    }
    sendSuccess(['message' => 'Выход выполнен']);
}

function authMe() {
    $userId = getCurrentUserId();
    if (!$userId) {
        sendError('Не авторизован', 401);
    }
    
    $user = getUserById($userId);
    unset($user['password']);
    sendSuccess($user);
}

// ============================================
// ПРОЕКТЫ
// ============================================

function getProjects() {
    $userId = getCurrentUserId();
    $user = getUserById($userId);
    
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT * FROM projects WHERE 1=1";
    $params = [];
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    // Сотрудники видят только свои проекты
    if ($user['role'] === 'employee') {
        $sql .= " AND id IN (SELECT project_id FROM sections WHERE assignee_id = ?)";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $projects = dbQuery($sql, $params);
    
    foreach ($projects as &$project) {
        $project['sections'] = dbQuery("SELECT * FROM sections WHERE project_id = ?", [$project['id']]);
        $project['progress'] = calculateProgress($project['sections']);
    }
    
    sendSuccess($projects);
}

function getProject() {
    $id = getIdFromPath($_GET['path']);
    
    if (!$id) {
        sendError('ID проекта не указан');
    }
    
    $project = dbQueryOne("SELECT * FROM projects WHERE id = ?", [$id]);
    
    if (!$project) {
        sendError('Проект не найден', 404);
    }
    
    $project['sections'] = dbQuery("SELECT * FROM sections WHERE project_id = ?", [$id]);
    $project['contacts'] = dbQuery("SELECT * FROM contacts WHERE project_id = ?", [$id]);
    $project['intro_blocks'] = dbQuery("SELECT * FROM intro_blocks WHERE project_id = ? ORDER BY sort_order", [$id]);
    $project['investigations'] = dbQuery("SELECT * FROM investigations WHERE project_id = ?", [$id]);
    $project['progress'] = calculateProgress($project['sections']);
    
    sendSuccess($project);
}

function createProject() {
    $userId = getCurrentUserId();
    $user = getUserById($userId);
    
    if (!in_array($user['role'], ['director', 'gip'])) {
        sendError('Нет прав для создания проекта', 403);
    }
    
    $data = getJsonInput();
    
    $id = dbInsert('projects', [
        'name' => $data['name'] ?? '',
        'code' => $data['code'] ?? '',
        'address' => $data['address'] ?? '',
        'type' => $data['type'] ?? 'construction',
        'deadline' => $data['deadline'] ?? null,
        'description' => $data['description'] ?? '',
        'status' => 'in_work',
        'gip_id' => $data['gip_id'] ?? null,
        'created_by' => $userId,
    ]);
    
    // Создаём разделы
    if (!empty($data['sections']) && is_array($data['sections'])) {
        foreach ($data['sections'] as $sectionCode) {
            dbInsert('sections', [
                'project_id' => $id,
                'code' => $sectionCode,
                'status' => 'not_started',
            ]);
        }
    }
    
    sendSuccess(['id' => $id, 'message' => 'Проект создан']);
}

function updateProject() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    dbUpdate('projects', $id, $data);
    
    sendSuccess(['message' => 'Проект обновлён']);
}

function deleteProject() {
    $id = getIdFromPath($_GET['path']);
    
    // Удаляем связанные данные
    dbQuery("DELETE FROM sections WHERE project_id = ?", [$id]);
    dbQuery("DELETE FROM contacts WHERE project_id = ?", [$id]);
    dbQuery("DELETE FROM intro_blocks WHERE project_id = ?", [$id]);
    dbQuery("DELETE FROM investigations WHERE project_id = ?", [$id]);
    dbQuery("DELETE FROM projects WHERE id = ?", [$id]);
    
    sendSuccess(['message' => 'Проект удалён']);
}

// ============================================
// РАЗДЕЛЫ
// ============================================

function getSection() {
    $id = getIdFromPath($_GET['path']);
    
    $section = dbQueryOne("SELECT * FROM sections WHERE id = ?", [$id]);
    
    if (!$section) {
        sendError('Раздел не найден', 404);
    }
    
    // Вводная информация проекта
    $section['intro_blocks'] = dbQuery(
        "SELECT * FROM intro_blocks WHERE project_id = ? ORDER BY sort_order", 
        [$section['project_id']]
    );
    
    // Файлы раздела
    $section['files'] = dbQuery("SELECT * FROM section_files WHERE section_id = ?", [$id]);
    
    // Готовый раздел
    $section['completed_file'] = dbQueryOne("SELECT * FROM section_files WHERE section_id = ? AND is_completed = 1", [$id]);
    
    sendSuccess($section);
}

function updateSectionStatus() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    $updateData = ['status' => $data['status'] ?? 'not_started'];
    
    if ($data['status'] === 'in_progress') {
        $updateData['started_at'] = date('Y-m-d H:i:s');
    } elseif ($data['status'] === 'completed') {
        $updateData['completed_at'] = date('Y-m-d H:i:s');
    }
    
    dbUpdate('sections', $id, $updateData);
    
    sendSuccess(['message' => 'Статус обновлён']);
}

function assignSection() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    dbUpdate('sections', $id, ['assignee_id' => $data['assignee_id'] ?? null]);
    
    sendSuccess(['message' => 'Исполнитель назначен']);
}

function uploadSectionFile() {
    $id = getIdFromPath($_GET['path']);
    
    if (empty($_FILES['file'])) {
        sendError('Файл не загружен');
    }
    
    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $filePath = 'uploads/' . uniqid() . '_' . $fileName;
    
    move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $filePath);
    
    $fileId = dbInsert('section_files', [
        'section_id' => $id,
        'name' => $fileName,
        'path' => $filePath,
        'is_completed' => 0,
    ]);
    
    sendSuccess(['id' => $fileId, 'name' => $fileName, 'path' => $filePath]);
}

function deleteSectionFile() {
    $sectionId = getIdFromPath($_GET['path']);
    $fileId = getIdFromPath($_GET['path'], 2);
    
    $file = dbQueryOne("SELECT * FROM section_files WHERE id = ? AND section_id = ?", [$fileId, $sectionId]);
    
    if ($file && file_exists(__DIR__ . '/../' . $file['path'])) {
        unlink(__DIR__ . '/../' . $file['path']);
    }
    
    dbQuery("DELETE FROM section_files WHERE id = ?", [$fileId]);
    
    sendSuccess(['message' => 'Файл удалён']);
}

function uploadCompletedSection() {
    $id = getIdFromPath($_GET['path']);
    
    if (empty($_FILES['file'])) {
        sendError('Файл не загружен');
    }
    
    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $filePath = 'uploads/' . uniqid() . '_' . $fileName;
    
    move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $filePath);
    
    // Удаляем старый готовый файл
    dbQuery("DELETE FROM section_files WHERE section_id = ? AND is_completed = 1", [$id]);
    
    $fileId = dbInsert('section_files', [
        'section_id' => $id,
        'name' => $fileName,
        'path' => $filePath,
        'is_completed' => 1,
    ]);
    
    sendSuccess(['id' => $fileId, 'name' => $fileName, 'path' => $filePath]);
}

function deleteCompletedSection() {
    $id = getIdFromPath($_GET['path']);
    
    $file = dbQueryOne("SELECT * FROM section_files WHERE section_id = ? AND is_completed = 1", [$id]);
    
    if ($file && file_exists(__DIR__ . '/../' . $file['path'])) {
        unlink(__DIR__ . '/../' . $file['path']);
    }
    
    dbQuery("DELETE FROM section_files WHERE section_id = ? AND is_completed = 1", [$id]);
    
    sendSuccess(['message' => 'Готовый раздел удалён']);
}

// ============================================
// ИЗЫСКАНИЯ
// ============================================

function getStandardInvestigations() {
    $items = dbQuery("SELECT * FROM standard_investigations ORDER BY name");
    sendSuccess($items);
}

function getProjectInvestigations() {
    $projectId = getIdFromPath($_GET['path']);
    $items = dbQuery("SELECT * FROM investigations WHERE project_id = ?", [$projectId]);
    sendSuccess($items);
}

function addInvestigation() {
    $projectId = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    $id = dbInsert('investigations', [
        'project_id' => $projectId,
        'standard_id' => $data['standard_id'] ?? null,
        'name' => $data['name'] ?? '',
        'status' => 'not_started',
        'contractor_name' => $data['contractor_name'] ?? '',
        'contractor_contact' => $data['contractor_contact'] ?? '',
        'contractor_phone' => $data['contractor_phone'] ?? '',
        'contractor_email' => $data['contractor_email'] ?? '',
        'contract_number' => $data['contract_number'] ?? '',
        'contract_date' => $data['contract_date'] ?? null,
        'start_date' => $data['start_date'] ?? null,
        'end_date' => $data['end_date'] ?? null,
        'description' => $data['description'] ?? '',
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Изыскание добавлено']);
}

function updateInvestigation() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    dbUpdate('investigations', $id, $data);
    
    sendSuccess(['message' => 'Изыскание обновлено']);
}

function deleteInvestigation() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("DELETE FROM investigations WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Изыскание удалено']);
}

// ============================================
// СОТРУДНИКИ
// ============================================

function getEmployees() {
    $items = dbQuery("SELECT * FROM users ORDER BY name");
    
    foreach ($items as &$item) {
        unset($item['password']);
        unset($item['token']);
    }
    
    sendSuccess($items);
}

function getEmployee() {
    $id = getIdFromPath($_GET['path']);
    $item = dbQueryOne("SELECT * FROM users WHERE id = ?", [$id]);
    
    if (!$item) {
        sendError('Сотрудник не найден', 404);
    }
    
    unset($item['password']);
    unset($item['token']);
    
    sendSuccess($item);
}

function createEmployee() {
    $data = getJsonInput();
    
    // Проверяем email
    $exists = dbQueryOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
    if ($exists) {
        sendError('Email уже используется');
    }
    
    $id = dbInsert('users', [
        'name' => $data['name'] ?? '',
        'email' => $data['email'] ?? '',
        'password' => hashPassword($data['password'] ?? '123456'),
        'role' => $data['role'] ?? 'employee',
        'position' => $data['position'] ?? '',
        'phone' => $data['phone'] ?? '',
        'competencies' => json_encode($data['competencies'] ?? []),
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Сотрудник создан']);
}

function updateEmployee() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    $updateData = [];
    foreach (['name', 'position', 'phone', 'competencies'] as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $field === 'competencies' ? json_encode($data[$field]) : $data[$field];
        }
    }
    
    if (!empty($data['password'])) {
        $updateData['password'] = hashPassword($data['password']);
    }
    
    dbUpdate('users', $id, $updateData);
    
    sendSuccess(['message' => 'Сотрудник обновлён']);
}

function deleteEmployee() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("UPDATE users SET is_archived = 1 WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Сотрудник архивирован']);
}

// ============================================
// ЗАДАЧИ
// ============================================

function getTasks() {
    $userId = getCurrentUserId();
    $user = getUserById($userId);
    
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT * FROM tasks WHERE 1=1";
    $params = [];
    
    // Сотрудники видят только свои задачи
    if ($user['role'] === 'employee') {
        $sql .= " AND assignee_id = ?";
        $params[] = $userId;
    }
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY deadline ASC, priority DESC";
    
    $items = dbQuery($sql, $params);
    sendSuccess($items);
}

function getTask() {
    $id = getIdFromPath($_GET['path']);
    $item = dbQueryOne("SELECT * FROM tasks WHERE id = ?", [$id]);
    
    if (!$item) {
        sendError('Задача не найдена', 404);
    }
    
    $item['comments'] = dbQuery("SELECT * FROM task_comments WHERE task_id = ? ORDER BY created_at", [$id]);
    
    sendSuccess($item);
}

function createTask() {
    $userId = getCurrentUserId();
    $data = getJsonInput();
    
    $id = dbInsert('tasks', [
        'title' => $data['title'] ?? '',
        'description' => $data['description'] ?? '',
        'project_id' => $data['project_id'] ?? null,
        'assignee_id' => $data['assignee_id'] ?? null,
        'deadline' => $data['deadline'] ?? null,
        'priority' => $data['priority'] ?? 'medium',
        'status' => 'not_started',
        'created_by' => $userId,
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Задача создана']);
}

function updateTask() {
    $id = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    dbUpdate('tasks', $id, $data);
    
    sendSuccess(['message' => 'Задача обновлена']);
}

function deleteTask() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("DELETE FROM tasks WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Задача удалена']);
}

// ============================================
// КОНТАКТЫ
// ============================================

function getContacts() {
    $projectId = getIdFromPath($_GET['path']);
    $items = dbQuery("SELECT * FROM contacts WHERE project_id = ?", [$projectId]);
    sendSuccess($items);
}

function addContact() {
    $projectId = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    $id = dbInsert('contacts', [
        'project_id' => $projectId,
        'name' => $data['name'] ?? '',
        'position' => $data['position'] ?? '',
        'company' => $data['company'] ?? '',
        'phone' => $data['phone'] ?? '',
        'email' => $data['email'] ?? '',
        'notes' => $data['notes'] ?? '',
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Контакт добавлен']);
}

function deleteContact() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("DELETE FROM contacts WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Контакт удалён']);
}

// ============================================
// ВВОДНАЯ ИНФОРМАЦИЯ
// ============================================

function getIntroBlocks() {
    $projectId = getIdFromPath($_GET['path']);
    $items = dbQuery("SELECT * FROM intro_blocks WHERE project_id = ? ORDER BY sort_order", [$projectId]);
    sendSuccess($items);
}

function addIntroBlock() {
    $projectId = getIdFromPath($_GET['path']);
    $data = getJsonInput();
    
    $filePath = null;
    $fileName = null;
    
    if (!empty($_FILES['file'])) {
        $file = $_FILES['file'];
        $fileName = basename($file['name']);
        $filePath = 'uploads/' . uniqid() . '_' . $fileName;
        move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $filePath);
    }
    
    $id = dbInsert('intro_blocks', [
        'project_id' => $projectId,
        'type' => $data['type'] ?? 'text',
        'title' => $data['title'] ?? '',
        'content' => $data['content'] ?? '',
        'file_path' => $filePath,
        'file_name' => $fileName,
        'sort_order' => $data['sort_order'] ?? 0,
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Блок добавлен']);
}

function deleteIntroBlock() {
    $id = getIdFromPath($_GET['path']);
    
    $block = dbQueryOne("SELECT * FROM intro_blocks WHERE id = ?", [$id]);
    if ($block && $block['file_path'] && file_exists(__DIR__ . '/../' . $block['file_path'])) {
        unlink(__DIR__ . '/../' . $block['file_path']);
    }
    
    dbQuery("DELETE FROM intro_blocks WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Блок удалён']);
}

// ============================================
// АДМИНКА
// ============================================

function adminGetInvestigations() {
    $items = dbQuery("SELECT * FROM standard_investigations ORDER BY name");
    sendSuccess($items);
}

function adminAddInvestigation() {
    $data = getJsonInput();
    
    $id = dbInsert('standard_investigations', [
        'name' => $data['name'] ?? '',
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Изыскание добавлено']);
}

function adminDeleteInvestigation() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("DELETE FROM standard_investigations WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Изыскание удалено']);
}

function adminGetSections() {
    $items = dbQuery("SELECT * FROM design_sections ORDER BY code");
    sendSuccess($items);
}

function adminAddSection() {
    $data = getJsonInput();
    
    $id = dbInsert('design_sections', [
        'code' => $data['code'] ?? '',
        'name' => $data['name'] ?? '',
        'is_active' => 1,
    ]);
    
    sendSuccess(['id' => $id, 'message' => 'Раздел добавлен']);
}

function adminDeleteSection() {
    $id = getIdFromPath($_GET['path']);
    dbQuery("DELETE FROM design_sections WHERE id = ?", [$id]);
    sendSuccess(['message' => 'Раздел удалён']);
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================

function calculateProgress($sections) {
    if (empty($sections)) return 0;
    $completed = 0;
    foreach ($sections as $section) {
        if ($section['status'] === 'completed') $completed++;
    }
    return round(($completed / count($sections)) * 100);
}
