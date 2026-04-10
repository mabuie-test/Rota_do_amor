# Rota do Amor

Plataforma premium de relacionamentos para Moçambique construída com **PHP 8+**, **MySQL 8**, **MVC**, **OOP/SOLID**, integração **Débito API (M-Pesa)** e emails transacionais com **PHPMailer**.

## Funcionalidades implementadas
- Registo, autenticação segura, logout, recuperação de senha e verificação de email.
- Ativação da conta por pagamento inicial, subscrição mensal e boost pago.
- Descoberta, swipe, match, mensagens, favoritos, bloqueios, denúncias e notificações.
- Feed social com posts, likes, comentários, paginação e media (upload múltiplo com thumbnails e ordenação).
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
6. Se a instância já existir, aplique migrações incrementais:
   ```bash
   mysql -u root -p < database/migrations/20260410_hardening.sql
   mysql -u root -p < database/migrations/20260410_consolidation_core.sql
   ```
7. Suba servidor local:
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
- `UPLOAD_MAX_IMAGE_SIZE` (bytes, default 5242880)

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

### Garantias de idempotência financeira
- Reconciliação processa cada pagamento em transação com lock (`SELECT ... FOR UPDATE`).
- Benefícios de pagamento são aplicados apenas uma vez por pagamento através de `benefit_application_status` + `benefit_applied_at`.
- Recuperação de estados legados só marca benefício como aplicado quando existe evidência forte (ex.: boost ligado por `payment_id` ou marca temporal consistente), mantendo pendente quando a evidência é fraca.
- Reconciliações repetidas de pagamentos já finalizados são ignoradas com logs explícitos.
- Pagamentos em estados finais (`completed`, `failed`, `cancelled`) não reexecutam efeitos indevidos.

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

## Segurança aplicada
- `password_hash` / `password_verify`
- Prepared statements via PDO
- CSRF obrigatório para `POST/PUT/PATCH/DELETE`, com suporte para campo `_token` e header `X-CSRF-TOKEN`
- Sessões seguras e regeneração de sessão
- RBAC admin validado em base por request (`AdminAuthorizationService`), com invalidação imediata de sessão em perda de acesso
- Throttling com logs de tentativa/sucesso/falha para login, feed e mensagens, persistido com colunas indexáveis (`rate_limit_key`, `rate_limit_outcome`)
- Autorização explícita em leitura de conversas (somente participantes)
- Uploads de imagens via `$_FILES` com validação de MIME real, tamanho máximo e nome aleatório seguro
- Controle de estado de conta (`pending_activation`, `active`, `expired`, `suspended`, `banned`)
- Logs de atividade, auditoria e logs financeiros

## Evoluções recentes de performance/produto
- Discovery sem N+1 para verificação/boost/premium/atividade, com ranking por compatibilidade e refresh incremental de scores.
- Inbox otimizada com joins agregados para última mensagem e não lidas (sem subqueries correlacionadas por item).
- Dashboard com distinção entre perfil completo vs atrativo, impacto de boost, progresso de verificação, contexto premium e ações prioritárias.
- Feed com paginação, payload enriquecido de autor, comentários recentes, upload real de múltiplas imagens e limpeza de media ao apagar post.
- Mensagens com histórico paginado, conversa ativa na inbox, contexto consistente do outro utilizador e anexos (`message_attachments`).
- Compatibilidade com menor round-trip por cálculo (interesses agregados e reutilização de dados alvo quando disponível).

## Índices e migração
As migrações incrementais atuais são:
- `database/migrations/20260410_hardening.sql`;
- `database/migrations/20260410_consolidation_core.sql`.

A segunda migração adiciona:
- índice de suporte à compatibilidade em `user_interests`;
- colunas indexáveis de throttling em `activity_logs`;
- metadados e ordenação de mídia em `post_images`;
- tabela `message_attachments` para anexos de conversa.

## Credenciais admin seed
- Email: `admin@rotadoamor.mz`
- Senha inicial: `Admin@123` (altere imediatamente)

## Estrutura resumida
- `app/Core`: núcleo MVC
- `app/Controllers`: controladores por domínio
- `app/Services`: regras de negócio e integrações externas
- `app/Models`: modelos/entidades
- `app/Views`: interface web
- `database/`: schema, seeds e migrações
- `scripts/`: automações operacionais
