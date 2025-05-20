<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // 1) Recebe e decodifica o JSON
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }

    // 2) Extrai e valida os campos
    $nome = htmlspecialchars(trim($data['nome'] ?? ''));
    $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($nome) || empty($email)) {
        throw new Exception('Campos nome e email são obrigatórios');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido');
    }

    // 3) Conexão com banco via PDO
    $host = "localhost";
    $dbname = "cliente";
    $user = "root";
    $pass = "";
    $charset = "utf8mb4";

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 4) Verifica se o e-mail já está cadastrado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registro WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Este e-mail já está cadastrado.');
    }

    // 5) Inserção no banco
    $stmt = $pdo->prepare("INSERT INTO registro (nome, email) VALUES (:nome, :email)");
    $stmt->execute([':nome' => $nome, ':email' => $email]);
    $insertId = $pdo->lastInsertId();

    // 6) Envio de e-mail via Mailjet
    $apiKey = '0aab2530d898ac2897b8e5821952cd94';
    $apiSecret = '6f0429cd00321483572f1745d32a2761';

    $mensagemTexto = "Olá $nome,\nAgradecemos por se cadastrar para receber nossas novidades.";
    $mensagemHtml = "
    <div style='font-family: Arial; padding: 20px; background: #f9f9f9; text-align: center;'>
        <h1>Olá <strong>$nome</strong>,</h1> 
        <h3>Agradecemos por se cadastrar para receber nossas novidades.<br>
        A partir de agora, você ficará por dentro de lançamentos, promoções exclusivas e mais.</h3><br>
        <img src='https://i.imgur.com/KH0bqMB.png' alt='Imagem' style='max-width: 100%; height: auto; display: block; margin: 0 auto;' /><br>
        <p>Atenciosamente,<br>TechGirls</p>
    </div>";

    $dadosEmail = [
        'Messages' => [[
            'From' => [
                'Email' => "suporte.techgirls@gmail.com",
                'Name' => "Suporte TechGirls"
            ],
            'To' => [[
                'Email' => $email,
                'Name' => $nome
            ]],
            'Subject' => "Cupom Liberado",
            'TextPart' => $mensagemTexto,
            'HTMLPart' => $mensagemHtml
        ]]
    ];

    $ch = curl_init("https://api.mailjet.com/v3.1/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dadosEmail),
        CURLOPT_USERPWD => "$apiKey:$apiSecret",
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"]
    ]);

    $resposta = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $respostaJson = json_decode($resposta, true);
    if ($status < 200 || $status >= 300) {
        $erro = $respostaJson['Messages'][0]['Errors'][0]['ErrorMessage'] ?? 'Erro desconhecido';
        throw new Exception("Erro ao enviar e-mail: $erro");
    }

    // 7) Retorno final
    echo json_encode([
        'success' => true,
        'insertId' => $insertId,
        'message' => 'Cadastrado com sucesso.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro no banco: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
