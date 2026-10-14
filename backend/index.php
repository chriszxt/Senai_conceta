```php
<?php
session_start();
require_once "config/database.php";
header("Content-Type: application/json; charset=UTF-8");

$rota = $_GET["rota"] ?? "";
$metodo = $_SERVER["REQUEST_METHOD"];

function resposta($dados) {
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function exigirLogin() {
    if (!isset($_SESSION["id_usuario"]))
        resposta(["sucesso" => false, "login" => true, "mensagem" => "Você precisa estar logado."]);
}

if ($rota === "sessao" && $metodo === "GET") {
    resposta(isset($_SESSION["id_usuario"]) ? [
        "logado" => true,
        "usuario" => [
            "id_usuario" => $_SESSION["id_usuario"],
            "nome" => $_SESSION["nome"],
            "username" => $_SESSION["username"],
            "foto" => $_SESSION["foto"]
        ]
    ] : ["logado" => false]);
}

if ($rota === "cadastro" && $metodo === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (!$nome || !$username || !$email || !$senha)
        resposta(["sucesso" => false, "mensagem" => "Preencha todos os campos."]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        resposta(["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);

    $stmt = $conn->prepare("SELECT username, email FROM usuario WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($u = $resultado->fetch_assoc()) {
        if ($u["username"] === $username)
            resposta(["sucesso" => false, "mensagem" => "Esse username já está em uso."]);
        if ($u["email"] === $email)
            resposta(["sucesso" => false, "mensagem" => "Esse e-mail já está cadastrado."]);
    }

    $foto = "avatar.png";

    if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, ["jpg", "jpeg", "png", "gif", "webp"]))
            resposta(["sucesso" => false, "mensagem" => "Formato de imagem não permitido."]);

        $arquivo = uniqid("usuario_") . "." . $ext;

        if (move_uploaded_file($_FILES["foto"]["tmp_name"], __DIR__ . "/uploads/" . $arquivo))
            $foto = $arquivo;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuario (nome, username, email, senha, foto, tipo_perfil) VALUES (?, ?, ?, ?, ?, 'usuario')");
    $stmt->bind_param("sssss", $nome, $username, $email, $hash, $foto);

    resposta($stmt->execute()
        ? ["sucesso" => true, "mensagem" => "Cadastro realizado com sucesso."]
        : ["sucesso" => false, "mensagem" => "Erro ao realizar cadastro."]);
}

if ($rota === "login" && $metodo === "POST") {
    $dados = json_decode(file_get_contents("php://input"), true) ?? [];
    $email = trim($dados["email"] ?? "");
    $senha = $dados["senha"] ?? "";

    if (!$email || !$senha)
        resposta(["sucesso" => false, "mensagem" => "Preencha o e-mail e a senha."]);

    $stmt = $conn->prepare("SELECT * FROM usuario WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario || !password_verify($senha, $usuario["senha"]))
        resposta(["sucesso" => false, "mensagem" => "Usuário ou senha inválidos."]);

    foreach (["id_usuario", "nome", "username", "foto"] as $campo)
        $_SESSION[$campo] = $usuario[$campo];

    resposta(["sucesso" => true, "mensagem" => "Login realizado com sucesso."]);
}

if ($rota === "logout" && $metodo === "POST") {
    session_unset();
    session_destroy();
    resposta(["sucesso" => true, "mensagem" => "Logout realizado com sucesso."]);
}

if ($rota === "publicacoes" && $metodo === "GET") {
    $id = $_SESSION["id_usuario"] ?? 0;

    $sql = "SELECT p.id_publicacao, p.id_usuario, p.texto, p.imagem,
            p.datahora_publicacao, u.nome, u.username, u.foto,
            COUNT(c.id_curtida) AS curtidas,
            MAX(CASE WHEN c.id_usuario = ? THEN 1 ELSE 0 END) AS usuario_curtiu
            FROM publicacao p
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN curtida c ON p.id_publicacao = c.id_publicacao
            GROUP BY p.id_publicacao, p.id_usuario, p.texto, p.imagem,
            p.datahora_publicacao, u.nome, u.username, u.foto
            ORDER BY p.datahora_publicacao DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    resposta($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($rota === "publicar" && $metodo === "POST") {
    exigirLogin();
    $texto = trim($_POST["texto"] ?? "");

    if (!$texto)
        resposta(["sucesso" => false, "mensagem" => "Digite um texto para publicar."]);

    $imagem = null;

    if (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, ["jpg", "jpeg", "png", "gif", "webp"]))
            resposta(["sucesso" => false, "mensagem" => "Formato de imagem não permitido."]);

        $arquivo = uniqid("publicacao_") . "." . $ext;

        if (!move_uploaded_file($_FILES["imagem"]["tmp_name"], __DIR__ . "/uploads/" . $arquivo))
            resposta(["sucesso" => false, "mensagem" => "Erro ao enviar a imagem."]);

        $imagem = $arquivo;
    }

    $id = $_SESSION["id_usuario"];
    $stmt = $conn->prepare("INSERT INTO publicacao (id_usuario, texto, imagem) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id, $texto, $imagem);

    resposta($stmt->execute()
        ? ["sucesso" => true, "mensagem" => "Publicação criada com sucesso."]
        : ["sucesso" => false, "mensagem" => "Erro ao criar publicação."]);
}

if ($rota === "curtir" && $metodo === "POST") {
    exigirLogin();
    $dados = json_decode(file_get_contents("php://input"), true) ?? [];
    $idPub = intval($dados["id_publicacao"] ?? 0);
    $idUser = $_SESSION["id_usuario"];

    if ($idPub <= 0)
        resposta(["sucesso" => false, "mensagem" => "Publicação inválida."]);

    $stmt = $conn->prepare("SELECT id_curtida FROM curtida WHERE id_usuario = ? AND id_publicacao = ?");
    $stmt->bind_param("ii", $idUser, $idPub);
    $stmt->execute();
    $curtida = $stmt->get_result()->num_rows > 0;

    if ($curtida) {
        $stmt = $conn->prepare("DELETE FROM curtida WHERE id_usuario = ? AND id_publicacao = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO curtida (id_usuario, id_publicacao) VALUES (?, ?)");
    }

    $stmt->bind_param("ii", $idUser, $idPub);
    $stmt->execute();

    resposta([
        "sucesso" => true,
        "curtida" => !$curtida,
        "mensagem" => $curtida ? "Curtida removida." : "Publicação curtida."
    ]);
}

if ($rota === "excluir" && $metodo === "POST") {
    exigirLogin();
    $dados = json_decode(file_get_contents("php://input"), true) ?? [];
    $idPub = intval($dados["id_publicacao"] ?? 0);
    $idUser = $_SESSION["id_usuario"];

    $stmt = $conn->prepare("SELECT imagem FROM publicacao WHERE id_publicacao = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $idPub, $idUser);
    $stmt->execute();
    $pub = $stmt->get_result()->fetch_assoc();

    if (!$pub)
        resposta(["sucesso" => false, "mensagem" => "Você não pode excluir esta publicação."]);

    if ($pub["imagem"] && file_exists(__DIR__ . "/uploads/" . $pub["imagem"]))
        unlink(__DIR__ . "/uploads/" . $pub["imagem"]);

    $stmt = $conn->prepare("DELETE FROM publicacao WHERE id_publicacao = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $idPub, $idUser);

    resposta($stmt->execute()
        ? ["sucesso" => true, "mensagem" => "Publicação excluída com sucesso."]
        : ["sucesso" => false, "mensagem" => "Erro ao excluir publicação."]);
}

if ($rota === "pesquisar" && $metodo === "GET") {
    $username = trim($_GET["username"] ?? "");

    if (!$username)
        resposta(["sucesso" => true, "usuarios" => []]);

    $pesquisa = "%$username%";
    $stmt = $conn->prepare("SELECT id_usuario, nome, username, foto FROM usuario WHERE username LIKE ? ORDER BY username");
    $stmt->bind_param("s", $pesquisa);
    $stmt->execute();

    resposta(["sucesso" => true, "usuarios" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

if ($rota === "perfil" && $metodo === "GET") {
    $username = trim($_GET["username"] ?? "");

    if (!$username)
        resposta(["sucesso" => false, "mensagem" => "Usuário não informado."]);

    $stmt = $conn->prepare("SELECT id_usuario, nome, username, foto, tipo_perfil FROM usuario WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario)
        resposta(["sucesso" => false, "mensagem" => "Usuário não encontrado."]);

    $id = $usuario["id_usuario"];

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM publicacao WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario["quantidade_publicacoes"] = $stmt->get_result()->fetch_assoc()["total"];

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM curtida c INNER JOIN publicacao p ON c.id_publicacao = p.id_publicacao WHERE p.id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario["quantidade_curtidas"] = $stmt->get_result()->fetch_assoc()["total"];

    $stmt = $conn->prepare("SELECT id_publicacao, texto, imagem, datahora_publicacao FROM publicacao WHERE id_usuario = ? ORDER BY datahora_publicacao DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario["publicacoes"] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    resposta(["sucesso" => true, "usuario" => $usuario]);
}

resposta(["sucesso" => false, "mensagem" => "Rota não encontrada."]);
```