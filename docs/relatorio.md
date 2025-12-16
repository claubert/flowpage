# Flowhedge — Auditoria e Arquitetura de Banco de Dados (MySQL)

## Resumo Executivo
- Schema completo em português criado com entidades de usuários, planos, assinaturas, pagamentos, sessões, notificações e Long & Short.
- Eventos diários automatizam expiração de assinaturas e lembretes de renovação; índices garantidos nas FKs.
- Controles de acesso: bloqueio automático por expiração; sessão segura com tokens; trilha de auditoria em `logs_acesso`.

## Inventário do Schema
- Tabelas: `usuarios`, `planos`, `assinaturas`, `pagamentos`, `sessoes`, `recuperacoes_senha`, `notificacoes`, `logs_acesso`, `estrategias_ls`, `sinais_ls`, `posicoes`, `acessos_recurso`.
- Chaves: todas as PKs declaradas; FKs com `CASCADE`/`RESTRICT` conforme relacionamento; índices em colunas FK.
- Tipos: `ENUM` padronizados em português; `JSON` para metadados em notificações.
- Codificação: `utf8mb4` com `collate utf8mb4_0900_ai_ci`.

## Problemas e Evidências
- Linhas órfãs: usar `db/flowhedge_auditoria.sql` para gerar SQL de contagem por FK e validar.
- FKs sem índice: consulta incluída em `db/flowhedge_auditoria.sql`; eventos reforçam criação idempotente.
- Padronização de nomes: todo schema em `snake_case` e português; migração `db/flowhedge_migracao_pt.sql` para quem já criou em inglês.

## Recomendações Prioritárias
- Quick wins:
  - Executar `db/flowhedge_schema_pt.sql` e `db/flowhedge_eventos.sql` no MySQL com `EVENT SCHEDULER` habilitado.
  - Popular `planos` com preços reais em `preco_centavos`.
- Médio prazo:
  - Integrar gateway (Stripe/Mercado Pago) na rota `/pagamento/confirmar` com validação assincrônica.
  - Adicionar máscara/criptografia para PII sensível, armazenando `senha_hash` com bcrypt.
- Longo prazo:
  - Monitorar índices não usados e remover duplicidades com base em métricas de consulta.
  - Expandir métricas operacionais e logs de auditoria com origem/ação detalhadas.

## ERD
- Arquivo `docs/flowhedge-erd-pt.mmd` com todas as entidades e FKs.

## Scripts Idempotentes
- Criação de schema: `db/flowhedge_schema_pt.sql`.
- Migração de nomes do inglês para português: `db/flowhedge_migracao_pt.sql`.
- Auditoria: `db/flowhedge_auditoria.sql`.
- Eventos e reforço de índices: `db/flowhedge_eventos.sql`.

## Execução
- Banco: `mysql -u <usuario> -p < db/flowhedge_schema_pt.sql` e demais arquivos.
- Aplicação: criar `.env` a partir de `.env.example`, instalar dependências, iniciar servidor.
- Rotas principais:
  - `/cadastro`, `/login`, `/logout`, `/assinar`, `/pagamento/confirmar`, `/painel`.

## Conformidade e Segurança
- Menor privilégio: usuário do BD com permissões somente de leitura/escrita no schema `flowhedge`.
- PII: `email`, IP, agente de usuário; evitar exposição em logs; considerar hashing adicional.
- Auditoria: `logs_acesso` com `sucesso`, IP e agente; notificações registradas.
- Backup/DR: política sugerida semanal, retenção 90 dias, testes mensais de restore.
- Timezone: usar `UTC` no BD e converter no app conforme necessidade.