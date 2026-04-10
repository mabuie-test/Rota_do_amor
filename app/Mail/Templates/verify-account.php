<h1>Verificação de Email</h1>
<p>Confirme sua conta no Rota do Amor:</p>
<?php
$verificationUrl = $verificationUrl ?? (rtrim((string) ($appUrl ?? 'http://127.0.0.1:8000'), '/') . '/email/verify/' . urlencode((string) ($token ?? '')));
?>
<p><a href="<?= e($verificationUrl) ?>">Verificar email</a></p>
