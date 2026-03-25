# Rota do Amor

Plataforma premium de relacionamentos para Moçambique com arquitetura MVC em PHP 8+, MySQL, integração Débito API (M-Pesa), subscrição recorrente, swipe/match, chat, feed social, moderação e emails transacionais com PHPMailer.

## Stack
- PHP 8.1+
- MySQL 8+
- Composer PSR-4
- Bootstrap 5 + JS modular
- PHPMailer

## Instalação
1. `cp .env.example .env`
2. Preencha credenciais de BD, Débito API e SMTP.
3. `composer install`
4. Execute SQL:
   - `mysql -u root -p < database/schema.sql`
   - `mysql -u root -p < database/mozambique_locations.sql`
   - `mysql -u root -p < database/seed.sql`
5. Servidor local:
   - `php -S localhost:8000 -t public`

## Fluxos de negócio
### Registo, verificação de email e ativação
1. Registo cria utilizador `pending_activation`.
2. `EmailVerificationService` gera token em `email_verifications` e dispara email.
3. Após confirmar email, utilizador faz pagamento de ativação (100 MZN via `.env`).
4. `PaymentService` confirma pagamento e atualiza conta.
5. `SubscriptionService` ativa ciclo inicial de 30 dias.

### Subscrição recorrente
- Renovação manual por `/subscription/renew`.
- Scripts CLI verificam pagamentos pendentes e expiram subscrições.
- Lembretes enviados por email antes da expiração.

### Premium e boost
- Boost pago via Débito API (`BOOST_PRICE`) e duração por `BOOST_DURATION_HOURS`.
- Registro histórico em `user_boosts` e `payments`.

### Recuperação de senha
- `PasswordResetService` cria token único com expiração curta.
- Tokens de uso único em `password_resets`.
- Senhas sempre com `password_hash`.

## Débito API
Variáveis obrigatórias:
- `DEBITO_BASE_URL`
- `DEBITO_TOKEN`
- `DEBITO_WALLET_ID`

`PaymentService` implementa:
- activation/subscription/boost/premium feature payment
- validação e normalização MSISDN Moçambique
- consulta de status e sincronização de estado interno

## PHPMailer
Configurar SMTP no `.env`:
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `MAIL_SUPPORT_ADDRESS`, `MAIL_SUPPORT_NAME`

Templates em `app/Mail/Templates/`.

## Scripts CLI (cron)
- `php scripts/check_pending_activation_payments.php`
- `php scripts/check_pending_subscription_payments.php`
- `php scripts/check_pending_boost_payments.php`
- `php scripts/expire_subscriptions.php`
- `php scripts/expire_boosts.php`
- `php scripts/send_subscription_reminders.php`
- `php scripts/cleanup_temp_uploads.php`

## Segurança
- PDO prepared statements
- CSRF token helper
- hashing seguro de senha
- sessões com regeneração de ID
- base para middleware de autorização
- logs de correio e atividade
- bloqueios/denúncias/moderação

## Estrutura MVC
- `app/Core` núcleo (Router, Request/Response, Auth, Config)
- `app/Controllers` fluxos HTTP
- `app/Services` regras de negócio
- `app/Models` entidades
- `app/Views` renderização
- `database/` schema + seeds
- `scripts/` jobs operacionais

## Credenciais admin seed
- Email: `admin@rotadoamor.mz`
- Senha: `Admin@123` (hash no seed, troque após primeiro login)
