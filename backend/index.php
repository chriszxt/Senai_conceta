<?php

session_start();

require_once "config/database.php";

header("Content-Type: application/json; charset=UTF-8");

$rota = $_GET["rota"] ?? "";
$metodo = $_SERVER["REQUEST_METHOD"];

function resposta($dados)
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function usuarioLogado()
{
    return isset($_SESSION["id_usuario"]);
}

function exigirLogin()
{
    if (!usuarioLogado()) {
        resposta([
            "sucesso" => false,
            "login" => true,
            "mensagem" => "Você precisa estar logado."
        ]);
    }
}


/* =========================
   STATUS DA SESSÃO
========================= */

if ($rota === "sessao" && $metodo === "GET") {

    if (usuarioLogado()) {

        resposta([
            "logado" => true,
            "usuario" => [
                "id_usuario" => $_SESSION["id_usuario"],
                "nome" => $_SESSION["nome"],
                "username" => $_SESSION["username"],
                "foto" => $_SESSION["foto"]
            ]
        ]);

    }

    resposta([
        "logado" => false
    ]);
}


/* =========================
   CADASTRO
========================= */

if ($rota === "cadastro" && $metodo === "POST") {

    $nome = trim($_POST["nome"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($nome === "" || $username === "" || $email === "" || $senha === "") {
        resposta([
            "sucesso" => false,
            "mensagem" => "Preencha todos os campos."
        ]);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Digite um e-mail válido."
        ]);
    }

    $sql = "SELECT id_usuario FROM usuario WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $existente = $resultado->fetch_assoc();

        $sqlUsername = "SELECT id_usuario FROM usuario WHERE username = ?";
        $stmtUsername = $conn->prepare($sqlUsername);
        $stmtUsername->bind_param("s", $username);
        $stmtUsername->execute();

        if ($stmtUsername->get_result()->num_rows > 0) {
            resposta([
                "sucesso" => false,
                "mensagem" => "Esse username já está em uso."
            ]);
        }

        resposta([
            "sucesso" => false,
            "mensagem" => "Esse e-mail já está cadastrado."
        ]);
    }

    $foto = "avatar.png";

    if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {

        $extensao = strtolower(
            pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION)
        );

        $permitidas = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($extensao, $permitidas)) {
            resposta([
                "sucesso" => false,
                "mensagem" => "Formato de imagem não permitido."
            ]);
        }

        $nomeArquivo = uniqid("usuario_") . "." . $extensao;

        $destino = __DIR__ . "/uploads/" . $nomeArquivo;

        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $destino)) {
            $foto = $nomeArquivo;
        }
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuario
            (nome, username, email, senha, foto, tipo_perfil)
            VALUES (?, ?, ?, ?, ?, 'usuario')";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sssss",
        $nome,
        $username,
        $email,
        $senhaHash,
        $foto
    );

    if ($stmt->execute()) {

        resposta([
            "sucesso" => true,
            "mensagem" => "Cadastro realizado com sucesso."
        ]);

    }

    resposta([
        "sucesso" => false,
        "mensagem" => "Erro ao realizar cadastro."
    ]);
}


/* =========================
   LOGIN
========================= */

if ($rota === "login" && $metodo === "POST") {

    $dados = json_decode(file_get_contents("php://input"), true);

    $email = trim($dados["email"] ?? "");
    $senha = $dados["senha"] ?? "";

    if ($email === "" || $senha === "") {
        resposta([
            "sucesso" => false,
            "mensagem" => "Preencha o e-mail e a senha."
        ]);
    }

    $sql = "SELECT * FROM usuario WHERE email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Usuário ou senha inválidos."
        ]);
    }

    $usuario = $resultado->fetch_assoc();

    if (!password_verify($senha, $usuario["senha"])) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Usuário ou senha inválidos."
        ]);
    }

    $_SESSION["id_usuario"] = $usuario["id_usuario"];
    $_SESSION["nome"] = $usuario["nome"];
    $_SESSION["username"] = $usuario["username"];
    $_SESSION["foto"] = $usuario["foto"];

    resposta([
        "sucesso" => true,
        "mensagem" => "Login realizado com sucesso."
    ]);
}


/* =========================
   LOGOUT
========================= */

if ($rota === "logout" && $metodo === "POST") {

    session_unset();
    session_destroy();

    resposta([
        "sucesso" => true,
        "mensagem" => "Logout realizado com sucesso."
    ]);
}


/* =========================
   FEED
========================= */

if ($rota === "publicacoes" && $metodo === "GET") {

    $idUsuario = $_SESSION["id_usuario"] ?? 0;

    $sql = "SELECT
                p.id_publicacao,
                p.id_usuario,
                p.texto,
                p.imagem,
                p.datahora_publicacao,
                u.nome,
                u.username,
                u.foto,
                COUNT(c.id_curtida) AS curtidas,
                MAX(
                    CASE
                        WHEN c.id_usuario = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS usuario_curtiu
            FROM publicacao p
            INNER JOIN usuario u
                ON p.id_usuario = u.id_usuario
            LEFT JOIN curtida c
                ON p.id_publicacao = c.id_publicacao
            GROUP BY
                p.id_publicacao,
                p.id_usuario,
                p.texto,
                p.imagem,
                p.datahora_publicacao,
                u.nome,
                u.username,
                u.foto
            ORDER BY p.datahora_publicacao DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $publicacoes = [];

    while ($linha = $resultado->fetch_assoc()) {
        $publicacoes[] = $linha;
    }

    resposta($publicacoes);
}


/* =========================
   CRIAR PUBLICAÇÃO
========================= */

if ($rota === "publicar" && $metodo === "POST") {

    exigirLogin();

    $texto = trim($_POST["texto"] ?? "");

    if ($texto === "") {
        resposta([
            "sucesso" => false,
            "mensagem" => "Digite um texto para publicar."
        ]);
    }

    $imagem = null;

    if (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK) {

        $extensao = strtolower(
            pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION)
        );

        $permitidas = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($extensao, $permitidas)) {
            resposta([
                "sucesso" => false,
                "mensagem" => "Formato de imagem não permitido."
            ]);
        }

        $nomeArquivo = uniqid("publicacao_") . "." . $extensao;

        $destino = __DIR__ . "/uploads/" . $nomeArquivo;

        if (!move_uploaded_file($_FILES["imagem"]["tmp_name"], $destino)) {
            resposta([
                "sucesso" => false,
                "mensagem" => "Erro ao enviar a imagem."
            ]);
        }

        $imagem = $nomeArquivo;
    }

    $idUsuario = $_SESSION["id_usuario"];

    $sql = "INSERT INTO publicacao
            (id_usuario, texto, imagem)
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "iss",
        $idUsuario,
        $texto,
        $imagem
    );

    if ($stmt->execute()) {
        resposta([
            "sucesso" => true,
            "mensagem" => "Publicação criada com sucesso."
        ]);
    }

    resposta([
        "sucesso" => false,
        "mensagem" => "Erro ao criar publicação."
    ]);
}


/* =========================
   CURTIR / DESCURTIR
========================= */

if ($rota === "curtir" && $metodo === "POST") {

    exigirLogin();

    $dados = json_decode(file_get_contents("php://input"), true);

    $idPublicacao = intval($dados["id_publicacao"] ?? 0);
    $idUsuario = $_SESSION["id_usuario"];

    if ($idPublicacao <= 0) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Publicação inválida."
        ]);
    }

    $sql = "SELECT id_curtida
            FROM curtida
            WHERE id_usuario = ?
            AND id_publicacao = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idUsuario, $idPublicacao);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $sql = "DELETE FROM curtida
                WHERE id_usuario = ?
                AND id_publicacao = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $idUsuario, $idPublicacao);
        $stmt->execute();

        resposta([
            "sucesso" => true,
            "curtida" => false,
            "mensagem" => "Curtida removida."
        ]);
    }

    $sql = "INSERT INTO curtida
            (id_usuario, id_publicacao)
            VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idUsuario, $idPublicacao);
    $stmt->execute();

    resposta([
        "sucesso" => true,
        "curtida" => true,
        "mensagem" => "Publicação curtida."
    ]);
}


/* =========================
   EXCLUIR PUBLICAÇÃO
========================= */

if ($rota === "excluir" && $metodo === "POST") {

    exigirLogin();

    $dados = json_decode(file_get_contents("php://input"), true);

    $idPublicacao = intval($dados["id_publicacao"] ?? 0);
    $idUsuario = $_SESSION["id_usuario"];

    $sql = "SELECT imagem
            FROM publicacao
            WHERE id_publicacao = ?
            AND id_usuario = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idPublicacao, $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Você não pode excluir esta publicação."
        ]);
    }

    $publicacao = $resultado->fetch_assoc();

    if (!empty($publicacao["imagem"])) {

        $arquivo = __DIR__ . "/uploads/" . $publicacao["imagem"];

        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }

    $sql = "DELETE FROM publicacao
            WHERE id_publicacao = ?
            AND id_usuario = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idPublicacao, $idUsuario);

    if ($stmt->execute()) {
        resposta([
            "sucesso" => true,
            "mensagem" => "Publicação excluída com sucesso."
        ]);
    }

    resposta([
        "sucesso" => false,
        "mensagem" => "Erro ao excluir publicação."
    ]);
}


/* =========================
   PESQUISAR USUÁRIOS
========================= */

if ($rota === "pesquisar" && $metodo === "GET") {

    $username = trim($_GET["username"] ?? "");

    if ($username === "") {
        resposta([
            "sucesso" => true,
            "usuarios" => []
        ]);
    }

    $pesquisa = "%" . $username . "%";

    $sql = "SELECT
                id_usuario,
                nome,
                username,
                foto
            FROM usuario
            WHERE username LIKE ?
            ORDER BY username";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pesquisa);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $usuarios = [];

    while ($linha = $resultado->fetch_assoc()) {
        $usuarios[] = $linha;
    }

    resposta([
        "sucesso" => true,
        "usuarios" => $usuarios
    ]);
}


/* =========================
   PERFIL
========================= */

if ($rota === "perfil" && $metodo === "GET") {

    $username = trim($_GET["username"] ?? "");

    if ($username === "") {
        resposta([
            "sucesso" => false,
            "mensagem" => "Usuário não informado."
        ]);
    }

    $sql = "SELECT
                id_usuario,
                nome,
                username,
                foto,
                tipo_perfil
            FROM usuario
            WHERE username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        resposta([
            "sucesso" => false,
            "mensagem" => "Usuário não encontrado."
        ]);
    }

    $usuario = $resultado->fetch_assoc();

    $idUsuario = $usuario["id_usuario"];

    $sql = "SELECT COUNT(*) AS total
            FROM publicacao
            WHERE id_usuario = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $publicacoes = $stmt->get_result()->fetch_assoc()["total"];

    $sql = "SELECT COUNT(*) AS total
            FROM curtida c
            INNER JOIN publicacao p
                ON c.id_publicacao = p.id_publicacao
            WHERE p.id_usuario = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $curtidas = $stmt->get_result()->fetch_assoc()["total"];

    $sql = "SELECT
                id_publicacao,
                texto,
                imagem,
                datahora_publicacao
            FROM publicacao
            WHERE id_usuario = ?
            ORDER BY datahora_publicacao DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $lista = [];

    while ($linha = $resultado->fetch_assoc()) {
        $lista[] = $linha;
    }

    $usuario["quantidade_publicacoes"] = $publicacoes;
    $usuario["quantidade_curtidas"] = $curtidas;
    $usuario["publicacoes"] = $lista;

    resposta([
        "sucesso" => true,
        "usuario" => $usuario
    ]);
}



resposta([
    "sucesso" => false,
    "mensagem" => "Rota não encontrada."
]);