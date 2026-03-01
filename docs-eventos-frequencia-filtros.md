# Lógica de recorrência de eventos e filtros do mapa

Este documento descreve como o plugin trata recorrência de eventos (`evento`) e como o filtro do mapa considera cada frequência.

## Frequências suportadas

Cada evento possui o meta `frequencia`, com um dos valores:

- `semanal`
- `mensal`
- `numero_semana`
- `anual`

Campos auxiliares por frequência:

- `dia_semana` (0..6, domingo..sábado)
- `dia_mes` (1..31)
- `numero_semana` (1..5)
- `mes` (1..12)

## Regras de correspondência por data

Dada uma data de referência, um evento acontece quando:

1. **Semanal** (`semanal`):
   - `dia_semana` do evento é igual ao dia da semana da data de referência.

2. **Mensal** (`mensal`):
   - `dia_mes` do evento é igual ao dia do mês da data de referência.

3. **Por número da semana** (`numero_semana`):
   - `dia_semana` igual ao dia da semana da data de referência; e
   - a ordem da semana no mês (`1..5`) da data de referência é igual a `numero_semana`.
   - Ex.: `numero_semana=2` e `dia_semana=2` = toda segunda terça-feira do mês.

4. **Anual** (`anual`):
   - `dia_mes` e `mes` do evento iguais ao dia/mês da data de referência.

## Filtros da API do mapa

A rota `/wp-json/mapa/v1/comunidades` aceita:

- `periodo=hoje`: considera a data atual.
- `periodo=semana`: considera os próximos 7 dias da semana corrente (segunda a domingo), retornando evento se houver ocorrência em qualquer dia.
- `periodo=data&data=YYYY-MM-DD`: considera a data selecionada.

Além disso, os filtros `tipo_evento`, `tipo_comunidade` e `tag` continuam aplicados normalmente.

## Exemplos práticos

- **Todo domingo**:
  - `frequencia=semanal`, `dia_semana=0`
- **Todo dia 13**:
  - `frequencia=mensal`, `dia_mes=13`
- **Toda 2ª terça-feira**:
  - `frequencia=numero_semana`, `numero_semana=2`, `dia_semana=2`
- **Todo dia 4 de outubro**:
  - `frequencia=anual`, `dia_mes=4`, `mes=10`

## Observações de manutenção

- Se a frequência não vier preenchida, o sistema assume `semanal` para compatibilidade.
- Em edição/salvamento, campos não usados pela frequência escolhida são limpos no meta.
- O frontend exibe a recorrência em linguagem natural com base nesses campos.
