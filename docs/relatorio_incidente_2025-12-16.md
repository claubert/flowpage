# Relatório de Incidente – 2025-12-16

## Resumo Executivo
- Sintoma: intermitência e queda do site, com respostas inconsistentes.
- Impacto: indisponibilidade parcial das funcionalidades de cadastro, pagamento e painel.
- Ação: ampliadas rotinas de observabilidade, adicionadas proteções contra falhas não capturadas, melhorados health checks e preparada mitigação de performance via índices.
- Status: serviço restaurado em desenvolvimento, com monitoramento profundo e medidas preventivas aplicadas.

## Linha do Tempo
- 10:00: Detecção de instabilidade pelos usuários.
- 10:10: Verificação de servidor e inicialização do serviço.
- 10:20: Revisão de rotas críticas e middleware de erros.
- 10:40: Implementação de `health/deep` e capturas de exceções globais.
- 11:00: Preparação de script idempotente de índices.

## Investigação
### Logs de Erro e Monitoramento
- Fonte: tabela `erros_sistema` e `logs_acesso` (MySQL).
- Ações:
  - Adicionada função de registro centralizado de erros e handlers para `unhandledRejection` e `uncaughtException`.
  - Health check profundo (`/health/deep`) reporta contagem de erros na última hora e pagamentos pendentes antigos.

### Infraestrutura e Recursos de Servidor
- Serviço Node/Express iniciado em `PORTA` configurada.
- Pool de conexões MySQL (`connectionLimit=10`) mantido com consultas curtas; não foram observadas saturações locais.

### Conectividade com Banco de Dados e Serviços Externos
- Banco: `SELECT 1` OK durante testes.
- Serviços externos: modo `mock` ativo, sem dependências externas ativas.

### Código e Atualizações Recentes
- Middleware de erro existia, porém rotas assíncronas sem captura podiam gerar rejeições não tratadas.
- Corrigido: captura global de exceções e erros assíncronos, além de rota de diagnóstico.
- Observação: uso de `notificacoes` no fluxo de recuperação de senha não impacta disponibilidade, mas será revisto futuramente.

## Causa Raiz (Provável)
- Rejeições assíncronas não tratadas em rotas podem gerar respostas inconsistentes sob erros de consulta, propagando ao cliente sem padronização.
- Health check básico não detectava sinais antecipados de falha na aplicação.

## Correções Implementadas
- Adicionado `ADMIN_KEY` e rota `/admin/erros` para inspeção segura de erros recentes.
- Ampliado `/health` com variante `/health/deep` para observabilidade operacional.
- Registrador de erros central e handlers de processo para capturar falhas não tratadas.
- Script idempotente `db/flowhedge_mitigacoes.sql` para criação de índices em colunas de FK e alto uso.

## Verificação
- Serviço iniciou e responde em `/health` e `/health/deep`.
- Rotas críticas testadas localmente sem queda.

## Medidas Preventivas
- Observabilidade: manter dashboard com `erros_ult_1h` e `pagamentos_pendentes_1h`.
- Resiliência: capturar e registrar exceções globais; padronizar resposta 500.
- Performance: aplicar índices de FK conforme script preparado.
- Governança: usar chave administrativa para acesso aos logs via API.

## Ações Recomendadas (Próximas)
- Implantar webhook PIX real e fila de notificações segregada por tipo.
- Adotar biblioteca para rotas assíncronas (ex.: `express-async-errors`) ou wrappers padronizados.
- Monitorar `connectionLimit` e métricas de latência de consultas.

## Itens Entregues
- Código atualizado (`src/app.js`): health profundo, captura global e endpoint de erros.
- Script SQL (`db/flowhedge_mitigacoes.sql`): índices idempotentes.

## Disponibilidade
- Ambiente de desenvolvimento com resposta consistente e sem quedas durante teste de fumaça.