# Rota do Amor

Plataforma premium de relacionamentos para Moçambique construída com **PHP 8+**, **MySQL 8**, **MVC**, **OOP/SOLID**, integração **Débito API (M-Pesa)** e emails transacionais com **PHPMailer**.

## Funcionalidades implementadas
- Registo, autenticação segura, logout, recuperação de senha e verificação de email.
- Ativação da conta por pagamento inicial, subscrição mensal e boost pago.
- Descoberta, swipe, match, mensagens, favoritos, bloqueios, denúncias e notificações.
- Convite com Intenção + Quem Gostou de Mim (convites standard/prioritário com snapshot de intenção, ritmo e compatibilidade).
- Encontro Seguro (ponte entre chat/match e encontro real com governança de estados, confiança e auditabilidade).
- Modo do Coração + Ritmo Relacional (camada de momento) com edição no perfil, chips na descoberta e contexto no dashboard/chat.
- Feed social com posts, likes, comentários, denúncia, paginação e media (upload múltiplo com thumbnails e ordenação).
- Verificação de identidade e badges de confiança.
- Painel admin consolidado com transição central de status, dashboards, pagamentos, subscrições, boosts, verificações, denúncias, moderação e configurações.
- Camada Super Admin com gestão de admins/papéis, centro de auditoria, dashboard executivo, centro de risco & abuso e analytics agregados do Diário do Coração.
- Área administrativa dedicada de Encontro Seguro (`/admin/safe-dates`) com listagem investigativa, filtros institucionais, paginação robusta e detalhe por encontro.
- Diário do Coração (privado/premium): criação, edição, listagem, detalhe, remoção, filtros por humor/período e resumo no dashboard pessoal.
- Rota Diária: missões diárias com progressão por ações reais (discovery, mensagens, convites, diário, feed, perfil e Encontro Seguro), streak e recompensa integrada (mini boost + badge).
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
5. Crie e popule a BD (instalação nova já fica alinhada com o estado atual do código):
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/mozambique_locations.sql
   mysql -u root -p < database/seed.sql
   ```
6. **Apenas para instâncias antigas** já existentes antes desta consolidação, aplique migrações incrementais:
   ```bash
   mysql -u root -p < database/migrations/20260410_hardening.sql
   mysql -u root -p < database/migrations/20260410_consolidation_core.sql
   mysql -u root -p < database/migrations/20260410_connection_modes.sql
   mysql -u root -p < database/migrations/20260411_connection_invites.sql
   mysql -u root -p < database/migrations/20260411_connection_invites_pending_uniqueness.sql
   mysql -u root -p < database/migrations/20260411_chat_realtime_receipts.sql
   mysql -u root -p < database/migrations/20260412_safe_dates_module.sql
   mysql -u root -p < database/migrations/20260412_safe_dates_consolidation.sql
   mysql -u root -p < database/migrations/20260412_safe_dates_admin_hardening.sql
   mysql -u root -p < database/migrations/20260413_daily_routes_module.sql
   mysql -u root -p < database/migrations/20260413_daily_routes_consolidation.sql
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
- `APP_URL` (base pública usada em links transacionais, ex.: `http://127.0.0.1:8000`)
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
php scripts/send_daily_route_nudges.php
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
- Política de ciclo de vida de media explícita e aplicada no código:
  - em falha de upload/validação/transação abortada, os ficheiros são removidos (rollback físico);
  - imagens de posts mantêm-se quando o post é apagado logicamente (`status='deleted'`);
  - anexos de mensagens mantêm-se enquanto a mensagem existir;
  - remoção física definitiva fica reservada para purge administrativo/rotina operacional.
- Controle de estado de conta (`pending_activation`, `active`, `expired`, `suspended`, `banned`)
- Logs de atividade, auditoria e logs financeiros

## Evoluções recentes de performance/produto
- Discovery sem N+1 para verificação/boost/premium/atividade, com ranking por compatibilidade e refresh incremental de scores.
- Inbox otimizada com joins agregados para última mensagem e não lidas (sem subqueries correlacionadas por item).
- Dashboard com distinção entre perfil completo vs atrativo, impacto de boost, progresso de verificação, contexto premium e ações prioritárias.
- Feed com paginação, payload enriquecido de autor, comentários recentes e ações de UI ligadas ao backend (like/comentário/denúncia/apagar).
- Mensagens com histórico paginado, conversa ativa na inbox, contexto do outro utilizador (presença/verificação/última atividade), anexos (`message_attachments`) e acabamento visual final.
- Chat em tempo real por SSE, recibos de envio/entrega/leitura (`sent_at`, `delivered_at`, `read_at`) e indicador de digitação efémero (`message_typing_states`).
- Compatibilidade com menor round-trip por cálculo (interesses agregados e reutilização de dados alvo quando disponível).


## Compatibilidade híbrida (estrutural + momento)
A compatibilidade mantém a base estrutural e adiciona camada emocional contextual:
- **65%**: score estrutural existente (localização, interesses, objetivo relacional, preferências e sinais de atividade/perfil).
- **20%**: alinhamento de **intenção atual** (`current_intention`).
- **15%**: alinhamento de **ritmo relacional** (`relational_pace`).

O `breakdown_json` em `compatibility_scores` agora inclui também:
- `current_intention`
- `relational_pace`

Com isso, perfis seguem visíveis mesmo sem alinhamento perfeito, mas o ranking prioriza melhor alinhamento de momento.

## Nova tabela incremental
Migração incremental: `database/migrations/20260410_connection_modes.sql`

Tabela adicionada:
- `user_connection_modes`
  - `user_id` único por utilizador
  - `current_intention`
  - `relational_pace`
  - `openness_level` (opcional)
  - timestamps + índices por intenção/ritmo


## Convite com Intenção + Quem Gostou de Mim
Fluxo complementar (sem substituir swipe/match) para interesse qualificado:
- `POST /invites/send`: envio de convite com snapshot do momento (`current_intention`, `relational_pace`, `compatibility_score`, `breakdown`).
- `GET /invites/received`: área **Quem Gostou de Mim** para aceitar/recusar e priorizar convites.
- `GET /invites/sent`: acompanhamento dos convites enviados.
- `POST /invites/accept`: aceita convite e cria/reactiva `match` + conversa.
- `POST /invites/decline`: recusa convite.

Regras aplicadas:
- bloqueia auto-convite, duplicado pendente, bloqueios entre utilizadores, utilizador inativo e throttling por hora/dia;
- convites prioritários exigem premium + mensagem de abertura;
- expiração configurável por `site_settings.invites_expiration_days` (default 7 dias);
- notificações para convite recebido, prioritário recebido, aceite e recusa.

Camada premium preparada:
- `invitation_type` (`standard`, `priority`) pronto para destaque e ordenação avançada;
- free vê recebidos com limite; premium vê lista completa e prioridade no topo.

## Encontro Seguro (V1)
Fluxo premium para transição segura do online ao encontro real:
- `GET /dates`, `GET /dates/{id}`, `POST /dates/propose`, `POST /dates/{id}/accept`, `POST /dates/{id}/decline`, `POST /dates/{id}/cancel`, `POST /dates/{id}/reschedule`, `POST /dates/{id}/reschedule/respond`, `POST /dates/{id}/arrived`, `POST /dates/{id}/finished-well`, `POST /dates/{id}/feedback`, `POST /dates/{id}/complete`.
- Entidade própria (`safe_dates`) com histórico (`safe_date_status_history`) e machine state no backend.
- Remarcação em 2 etapas sem ambiguidade: `reschedule_requested` (pedido pendente) → `rescheduled` apenas após aceite da contraparte.
- Pós-encontro V1: check-in de chegada, confirmação de término seguro e feedback privado (`safe_date_private_feedback`) com sinalização de risco institucional.
- Regras de elegibilidade: par com `match` ativo ou `connection_invite` aceite, bloqueios respeitados, contas `active`, proteção anti-duplicado em aberto e throttling de abuso.
- Camada de confiança: `safety_level` (`standard`, `verified_only`, `premium_guard`), badges/verificação no detalhe do encontro e checklist de segurança institucional.
- Integração nativa com chat: CTA para propor encontro direto na conversa e acesso rápido ao encontro ativo por `conversation_id`.
- Integração institucional: notificações (`safe_date_*`), eventos em auditoria (`safe_date_created|accepted|declined|cancelled|reschedule_requested|rescheduled|completed|expired|reminder_sent|arrived|finished_well|feedback_submitted`) e sinais no centro de risco/super dashboard.
- Super/Admin: área dedicada para governança e investigação (`GET /admin/safe-dates`, `GET /admin/safe-dates/{id}`) com ponte direta para users/audit/risk/moderação.
- Camada premium explícita e operacional por `site_settings` (`safe_dates_free_daily_limit`, `safe_dates_premium_daily_limit`, `safe_dates_max_open_free`, `safe_dates_max_open_premium`, `safe_dates_premium_guard_enabled`, `safe_dates_verified_only_requires_identity`).
- Lembretes automáticos por script operacional (`scripts/send_safe_date_reminders.php`) em janelas de 24h, 2h e mesmo dia (com proteção anti-duplicado por colunas de envio).

## Índices e migração
`database/schema.sql` já inclui o estado consolidado atual (incluindo `user_connection_modes`, `activity_logs.rate_limit_key`, `activity_logs.rate_limit_outcome`, metadados de `post_images`, `message_attachments` e blindagem estrutural para existir no máximo 1 convite `pending` por par remetente→destinatário) para instalações novas.

As migrações incrementais atuais (uso exclusivo em bases antigas) são:
- `database/migrations/20260410_hardening.sql`;
- `database/migrations/20260410_consolidation_core.sql`;
- `database/migrations/20260410_connection_modes.sql`;
- `database/migrations/20260411_connection_invites.sql`;
- `database/migrations/20260411_connection_invites_pending_uniqueness.sql`;
- `database/migrations/20260411_chat_realtime_receipts.sql`.
- `database/migrations/20260412_safe_dates_module.sql`;
- `database/migrations/20260412_safe_dates_consolidation.sql`.
- `database/migrations/20260412_safe_dates_admin_hardening.sql`;
- `database/migrations/20260413_daily_routes_module.sql`.
- `database/migrations/20260413_daily_routes_consolidation.sql`.

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


## Rota Diária (retenção e hábito)
- Rotas de utilizador:
  - `GET /daily-route`
  - `POST /daily-route/claim-reward`
- Entidades: `daily_routes`, `daily_route_tasks`, `daily_route_rewards`, `daily_route_streaks`.
- Observabilidade de eventos reais: `daily_route_event_logs` (evento + módulo de origem + incremento).
- Geração idempotente: 1 rota por utilizador/dia com `UNIQUE(user_id, route_date)`.
- Progresso por eventos reais: discovery/swipe, envio de mensagem, convite com intenção, diário, feed, atualização de perfil/modo e ações de Encontro Seguro.
- Streak robusta: `current_streak`, `best_streak`, perda automática quando há falha de sequência.
- Recompensa V1 configurável por `site_settings`:
  - `daily_route_reward_boost_hours`
  - `daily_route_reward_badge_type`
- Camada premium e retenção configurável por `site_settings`:
  - `daily_route_reward_boost_hours_premium`
  - `daily_route_reward_badge_days`
  - `daily_route_reward_badge_days_premium`
  - `daily_route_streak_bonus_threshold`
  - `daily_route_streak_bonus_boost_hours`
  - `daily_route_premium_streak_bonus_threshold`
  - `daily_route_premium_streak_bonus_boost_hours`
  - `daily_route_premium_discovery_priority_hours`
  - `daily_route_target_discover_active`
  - `daily_route_target_discover_default`
  - `daily_route_target_feed_interactions`
  - `daily_route_target_premium_momentum`
  - `daily_route_nudge_end_of_day_hour`
  - `daily_route_nudge_inactive_days`
  - `daily_route_nudge_streak_risk_min_streak`
  - `daily_route_nudge_new_user_window_days`
- Flags de preparo do hub (desligadas por padrão até os módulos existirem):
  - `daily_route_enable_visitors_hub_task`
  - `daily_route_enable_anonymous_stories_task`
  - `daily_route_enable_compatibility_duel_task`
- Nudges e lembretes com anti-spam (`daily_route_nudge_logs`) por segmentos: novo, inativo, premium, sem diário, matches sem conversa e perfil incompleto.
- Hub preparado para próximos módulos via taxonomia de eventos no domínio da rota:
  - Radar de Visitantes (`visitor_profile_viewed`, `visitor_profile_engaged`)
  - Histórias Anónimas (`anonymous_story_published`, `anonymous_story_interacted`)
  - Duelo de Compatibilidade (`compatibility_duel_joined`, `compatibility_duel_voted`, `compatibility_duel_action_taken`)
- Dashboard pessoal: bloco com progresso do dia, sequência atual, recompensa e CTA para continuar.
- Super dashboard: adoção da Rota Diária, taxa de conclusão, utilizadores com streak ativa, taxa de claim de recompensa e leitura premium/free para governança.
- Super dashboard: inclui também cobertura de instrumentação por módulo para validar integração real (discover/swipe/messages/invites/diary/feed/profile/safe_dates).

## Diário do Coração (privado)
- Rotas de utilizador:
  - `GET /diary`
  - `GET /diary/new`
  - `POST /diary`
  - `GET /diary/{id}`
  - `POST /diary/{id}`
  - `POST /diary/{id}/archive`
  - `POST /diary/{id}/delete`
- Campos principais da entrada:
  - `title`, `content`, `mood`, `emotional_state`, `relational_focus`, `visibility`
  - `intention_snapshot`, `relational_pace_snapshot`, `tags_json`
- Privacidade: por padrão `visibility=private` e conteúdo não é exposto para outros utilizadores ou admins comuns.
- Dashboard do utilizador: bloco integrado com CTA contextual, prompt de jornada, snapshot do Modo do Coração e resumo curto do último registo.
- Super Admin: visão apenas agregada/institucional (adoção, frequência e retenção) sem leitura de conteúdo íntimo.

## Super Admin (novos centros)
- `GET /admin/super-dashboard`: visão macro do negócio + alertas críticos.
- `GET /admin/admins`: gestão de admins, papéis e ativação/inativação.
- `GET /admin/audit`: centro de auditoria global com filtros por actor, acção, alvo e período.
- `GET /admin/risk`: centro de risco/abuso com contas suspeitas por sinais agregados.
- `GET /admin/safe-dates`: centro administrativo do Encontro Seguro (filtros por status/período/safety level/par de utilizadores).

## Operação rápida (checklist)
1. Verificar saúde: login admin, dashboard executivo, denúncias pendentes e pagamentos falhados.
2. Validar filas operacionais: verificações de identidade pendentes e reconciliação financeira.
3. Monitorar auditoria: mudanças de status, ações sensíveis e alterações em `site_settings`.
4. Revisar risco: utilizadores com reincidência de denúncias/bloqueios e anomalias de mensagens.
5. Confirmar retenção: métricas agregadas do Diário do Coração no super dashboard.
