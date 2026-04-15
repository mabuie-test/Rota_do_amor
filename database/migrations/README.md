# Migrations históricas após consolidação do `database/schema.sql`

Desde **2026-04-15**, o ficheiro `database/schema.sql` é o snapshot autoritativo para **instalações novas**.

## Como interpretar as migrations existentes

- As migrations em `database/migrations/*.sql` permanecem no repositório como histórico e para upgrades de bases legadas.
- Para uma instalação limpa, **não** é necessário executar a cadeia histórica de migrations: basta importar `database/schema.sql`.
- Migrations com lógica de normalização/backfill (ex.: merge de pares antigos em `matches`/`conversations`, ajustes de `rate_limit_key`) continuam úteis apenas para bases já em produção com dados antigos.

## Regra daqui para frente

- Alterações estruturais novas devem atualizar o `database/schema.sql` para manter a base autoritativa consolidada.
- Se necessário para ambientes legados, uma migration incremental correspondente pode ser adicionada em paralelo.
