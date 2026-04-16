# Migrations históricas após consolidação do `database/schema.sql`

Desde **2026-04-15**, o ficheiro `database/schema.sql` é o snapshot autoritativo para **instalações novas**.

## Política operacional (curta)

- **Instalação nova:** importar apenas `database/schema.sql`.
- **Base legada existente:** aplicar migrations incrementais necessárias em `database/migrations/*.sql`.
- Migrations com backfill/correções históricas **não** devem ser executadas numa base nova.
- Qualquer alteração estrutural futura deve atualizar **em paralelo**:
  1. `database/schema.sql` (fonte de verdade para novos ambientes)
  2. migration incremental (apenas quando necessário para upgrade de legados).
