<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Shanghai');
if (empty($_SESSION['token']))
  $_SESSION['token'] = bin2hex(random_bytes(32));
function h($v): string
{
  return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
try {
  $db = new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable $e) {
  http_response_code(503);
  exit('数据库暂时不可用，请检查服务是否启动。');
}
$error = '';
$softwareTypes = [
  'web' => '网页版',
  'windows' => '桌面应用 Windows',
  'mac' => '桌面应用 Mac',
  'android' => 'Android App',
  'ios' => 'iOS App',
  'wechat' => '微信小程序',
];
$selectedType = 'web';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
    http_response_code(403);
    exit('请求已失效，请刷新页面。');
  }
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $selectedType = $_POST['software_type'] ?? '';
    if ($title === '' || preg_match_all("/./us", $title) > 200)
      $error = '请输入软件题目，最多 200 个字符。';
    elseif (!is_string($selectedType) || !array_key_exists($selectedType, $softwareTypes))
      $error = '请选择有效的软件类型。';
    else {
      $stmt = $db->prepare('INSERT INTO tasks(title, software_type) VALUES (?, ?)');
      $stmt->execute([$title, $selectedType]);
      header('Location: /?created=1');
      exit;
    }
  } elseif ($action === 'delete') {
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND status = 'pending'");
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    header('Location: /?deleted=1');
    exit;
  }
}
$page = ($_GET['page'] ?? '') === 'new' ? 'new' : 'list';
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$allowed = ['pending', 'running', 'completed', 'failed'];
$where = [];
$params = [];
if ($q !== '') {
  $where[] = 'title LIKE ?';
  $params[] = '%' . $q . '%';
}
if (in_array($status, $allowed, true)) {
  $where[] = 'status = ?';
  $params[] = $status;
}
$sql = 'SELECT * FROM tasks' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC, id DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$counts = $db->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='running') running, SUM(status='completed') completed FROM tasks")->fetch(PDO::FETCH_ASSOC);
$labels = ['pending' => '待生成', 'running' => '生成中', 'completed' => '已完成', 'failed' => '生成失败'];
?>
<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>软著材料任务台</title>
  <link rel="stylesheet" href="/style.css">
</head>

<body>
  <aside class="sidebar"><a class="brand" href="/"><span
        class="brand-mark">著</span><span>软著任务台<small>材料管理工作区</small></span></a>
    <div class="nav-label">工作区</div>
    <nav><a class="<?= $page === 'new' ? 'active' : '' ?>" href="/?page=new"><span>＋</span> 新建任务</a><a
        class="<?= $page === 'list' ? 'active' : '' ?>" href="/"><span>▤</span> 生成列表</a></nav>
    <div class="side-note"><span class="dot"></span> 本地工作区 <small>数据保存在本机 Docker 环境</small></div>
  </aside>
  <main>
    <header class="top">
      <div>工作区 <span> / </span> <?= $page === 'new' ? '新建任务' : '生成列表' ?></div>
      <div class="local"><span class="dot"></span> 本地运行</div>
    </header>
    <div class="content">
      <?php if ($page === 'new'): ?>
        <div class="heading">
          <div>
            <div class="eyebrow">NEW TASK</div>
            <h1>新建任务</h1>
            <p>输入软件题目，创建一条材料准备任务。</p>
          </div><a class="button light" href="/">查看生成列表 <span>→</span></a>
        </div>
        <div class="new-layout">
          <section class="card form-card">
            <div class="section-top">
              <div class="icon-box">✦</div>
              <div>
                <h2>软件基本信息</h2>
                <p>先记录题目，后续可在列表中查看进度。</p>
              </div>
            </div>
            <form method="post"><input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>"><input type="hidden"
                name="action" value="create"><label for="title">软件著作权题目 <b>*</b></label><input id="title" name="title"
                maxlength="200" required autofocus placeholder="例如：设备巡检管理系统" value="<?= h($_POST['title'] ?? '') ?>">
              <div class="hint">填写完整的软件名称，最多 200 个字符。</div>
              <label class="type-label" for="software_type">软件类型 <b>*</b></label>
              <select id="software_type" name="software_type" required>
                <?php foreach ($softwareTypes as $value => $name): ?>
                  <option value="<?= h($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($error): ?>
                <p class="error"><?= h($error) ?></p><?php endif; ?>
              <div class="form-actions"><button class="button primary" type="submit">创建任务 <span>→</span></button></div>
            </form>
          </section>
          <aside class="card info-card">
            <div class="info-icon">i</div>
            <h3>关于当前版本</h3>
            <p>创建后，任务将保存在生成列表中，状态为“待生成”。</p>
            <div class="separator"></div>
            <p>材料生成功能尚未接入，暂不会生成文件。</p>
          </aside>
        </div>
      <?php else: ?>
        <div class="heading">
          <div>
            <div class="eyebrow">YOUR WORKSPACE</div>
            <h1>生成列表</h1>
            <p>查看和管理你的软件材料任务。</p>
          </div><a class="button primary" href="/?page=new">＋ 新建任务</a>
        </div>
        <?php if (isset($_GET['created'])): ?>
          <div class="notice">任务已创建，材料生成功能尚未接入。</div><?php elseif (isset($_GET['deleted'])): ?>
          <div class="notice">任务记录已删除。</div><?php endif; ?>
        <div class="stats">
          <div class="stat"><span>全部任务</span><strong><?= h($counts['total']) ?></strong><i>▤</i></div>
          <div class="stat"><span>待生成</span><strong><?= h($counts['pending'] ?? 0) ?></strong><i class="amber">◷</i></div>
          <div class="stat"><span>生成中</span><strong><?= h($counts['running'] ?? 0) ?></strong><i class="blue">◌</i></div>
          <div class="stat"><span>已完成</span><strong><?= h($counts['completed'] ?? 0) ?></strong><i class="green">✓</i></div>
        </div>
        <section class="card table-card">
          <div class="table-head">
            <div>
              <h2>任务记录</h2>
              <p>最近的任务及材料状态</p>
            </div>
            <form class="filters" method="get"><input name="q" aria-label="搜索软件题目" placeholder="搜索软件题目"
                value="<?= h($q) ?>"><select name="status" aria-label="筛选状态">
                <option value="">全部状态</option><?php foreach ($labels as $key => $label): ?>
                  <option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
              </select><button class="button light" type="submit">搜索</button></form>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>软件题目</th>
                  <th>软件类型</th>
                  <th>生成状态</th>
                  <th>新建时间</th>
                  <th>完成时间</th>
                  <th>材料位置</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody><?php foreach ($tasks as $task): ?>
                  <tr>
                    <td>
                      <div class="task-title"><span class="file-icon">▧</span>
                        <div><strong><?= h($task['title']) ?></strong><small>任务 #<?= h($task['id']) ?></small></div>
                      </div>
                    </td>
                    <td><?= h($softwareTypes[$task['software_type']] ?? $task['software_type']) ?></td>
                    <td><span class="badge <?= h($task['status']) ?>"><span>●</span>
                        <?= h($labels[$task['status']] ?? $task['status']) ?></span></td>
                    <td><?= h($task['created_at']) ?></td>
                    <td><?= h($task['completed_at'] ?? '—') ?></td>
                    <td><?= h($task['output_path'] ?: '—') ?></td>
                    <td><?php if ($task['status'] === 'pending'): ?>
                        <form method="post" onsubmit="return confirm('确定删除这条任务记录吗？')"><input type="hidden" name="token"
                            value="<?= h($_SESSION['token']) ?>"><input type="hidden" name="action" value="delete"><input
                            type="hidden" name="id" value="<?= h($task['id']) ?>"><button class="delete"
                            type="submit">删除</button></form><?php else: ?>—<?php endif; ?>
                    </td>
                  </tr><?php endforeach; ?>
              </tbody>
            </table><?php if (!$tasks): ?>
              <div class="empty">
                <div class="empty-icon">▤</div><strong><?= ($q !== '' || $status !== '') ? '没有找到匹配的任务' : '还没有任务' ?></strong>
                <p><?= ($q !== '' || $status !== '') ? '试试其他关键词或状态。' : '从新建任务开始，记录你的软件题目。' ?></p>
                <?php if ($q === '' && $status === ''): ?><a href="/?page=new" class="button light">＋ 创建第一个任务</a><?php endif; ?>
              </div><?php endif; ?>
          </div>
          <div class="table-foot">显示最近 <?= count($tasks) ?> 条记录 <span>材料生成服务待接入</span></div>
        </section>
      <?php endif; ?>
      <footer>软著材料任务台 <span>·</span> 本地工作区</footer>
    </div>
  </main>
</body>

</html>
