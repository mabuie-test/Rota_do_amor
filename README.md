# Rota do Amor

Plataforma premium de relacionamentos para Moçambique construída com **PHP 8+**, **MySQL**, **MVC**, **OOP/SOLID**, integração **Débito API (M-Pesa)** e emails transacionais com **PHPMailer**.

## Funcionalidades implementadas
- Registo, autenticação segura, logout, recuperação de senha e verificação de email.
- Ativação da conta por pagamento inicial, subscrição mensal e boost pago.
- Descoberta, swipe, match, mensagens, favoritos, bloqueios, denúncias e notificações.
- Feed social com posts, likes e comentários.
- Verificação de identidade e badges de confiança.
- Painel admin com dashboards, pagamentos, subscrições, boosts, verificações, denúncias, moderação e configurações.
- Scripts CLI para reconciliação de pagamentos, expiração de subscrições/boosts e envio de lembretes.

## Requisitos
- PHP 8.1+
- MySQL 8+
- Extensões PHP: `pdo_mysql`, `curl`, `json`, `mbstring`
- Composer

## Instalação
1. Clone o projeto.
2. Instale dependências:
   ```bash
   composer install
   ```
3. Configure ambiente:
   ```bash
   cp .env.example .env
   ```
4. Ajuste variáveis no `.env` (DB, Débito API, SMTP e pricing).
5. Crie e popule a BD:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/mozambique_locations.sql
   mysql -u root -p < database/seed.sql
   ```
6. Suba servidor local:
   ```bash
   php -S localhost:8000 -t public
   ```

## Configuração via `.env`
Preços e regras de negócio são carregados por ambiente (sem hardcode operacional):
- `ACTIVATION_PRICE`
- `MONTHLY_SUBSCRIPTION_PRICE`
- `BOOST_PRICE`
- `BOOST_DURATION_HOURS`
- `SUBSCRIPTION_DURATION_DAYS`
- `EMAIL_VERIFICATION_REQUIRED`
- `PASSWORD_RESET_TOKEN_EXPIRY_MINUTES`
- `EMAIL_VERIFICATION_TOKEN_EXPIRY_HOURS`

## Débito API (M-Pesa)
Configurar:
- `DEBITO_BASE_URL`
- `DEBITO_TOKEN`
- `DEBITO_WALLET_ID`

Fluxos cobertos:
- Ativação inicial
- Renovação de subscrição
- Compra de boost
- Consulta de estado por referência

## PHPMailer / SMTP
Configurar:
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `MAIL_SUPPORT_ADDRESS`, `MAIL_SUPPORT_NAME`

Templates em: `app/Mail/Templates/`.

## Cron jobs recomendados
```bash
php scripts/check_pending_activation_payments.php
php scripts/check_pending_subscription_payments.php
php scripts/check_pending_boost_payments.php
php scripts/expire_subscriptions.php
php scripts/expire_boosts.php
php scripts/send_subscription_reminders.php
php scripts/cleanup_temp_uploads.php
```

## Fluxo de negócio principal
1. Utilizador registra-se.
2. Recebe email de verificação.
3. Verifica email.
4. Efetua pagamento de ativação.
5. Conta ativa e subscrição inicial inicia.
6. Pode usar descoberta, swipe, match, chat, feed e recursos premium.


## Consistência de estados (revisão final)
- `AccountStateService` centraliza regras de estado entre email verificado, ativação paga, subscrição e bloqueios administrativos.
- Middlewares de conta/subscrição/email garantem permissões coerentes por rota.
- Reconciliação de pagamentos atualiza estados internos e badges.
- Expiração de subscrições/boosts sincroniza estado do utilizador e badges automaticamente.

## Segurança aplicada
- `password_hash` / `password_verify`
- Prepared statements via PDO
- CSRF token helper
- Sessões seguras e regeneração de sessão
- Controle de estado de conta (`pending_activation`, `active`, `expired`, `suspended`, `banned`)
- Logs de atividade e moderação

## Credenciais admin seed
- Email: `admin@rotadoamor.mz`
- Senha inicial: `Admin@123` (altere imediatamente)

## Estrutura resumida
- `app/Core`: núcleo MVC
- `app/Controllers`: controladores por domínio
- `app/Services`: regras de negócio e integrações externas
- `app/Models`: modelos/entidades
- `app/Views`: interface web
- `database/`: schema e seeds
- `scripts/`: automações operacionais
