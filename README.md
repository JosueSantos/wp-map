# WP MAP — Sistema de Comunidades e Eventos com Mapa

Plugin WordPress para cadastro de **Comunidades (Paróquias, Capelas, Associações)** e seus **Eventos (Missas, Confissões, etc)** com API pública para alimentar mapas, apps ou outros sites.

O projeto foi desenvolvido com foco em:

- Estrutura escalável
- API desacoplada
- Filtros inteligentes
- Integração futura com mapa interativo
- Cadastro via formulário

---

# Estrutura de Dados

## Post Types

### Comunidade (`comunidade`)

Representa:
- Paróquia
- Capela
- Associação independente

Cada **Capela pertence a uma paróquia**.

### Evento (`evento`)

Representa:
- Missa
- Confissão
- Adoração
- Evento Social
- Outros

Cada **Evento pertence a uma Comunidade**.

---

# Campos das Comunidades

| Campo | Tipo | Descrição |
|------|------|-----------|
| latitude | string | Coordenada GPS |
| longitude | string | Coordenada GPS |
| endereco | string | Endereço textual |
| parent_paroquia | integer | ID da Paróquia (se for Capela) |
| contatos | array | Lista dinâmica de contatos/links |
| tipo_comunidade | taxonomy | Paróquia / Capela / Associação |

### Campo `contatos`

Estrutura:

```json
[
  { "tipo": "telefone", "valor": "(85) 99999-9999" },
  { "tipo": "instagram", "valor": "https://instagram.com/paroquia" }
]
```
### Tipos possíveis de contato:
- telefone
- whatsapp
- instagram
- facebook
- youtube
- site
- email
- outro

# Campos dos Eventos
| Campo | Tipo | Descrição |
|------|------|-----------|
| comunidade_id	| integer	| Comunidade dona do evento| 
| dia_semana	| string	| Domingo → Sábado | 
| horario	    | string	| Hora do evento | 
| descricao	    | string	| Descrição | 
| observacao	| string	| Observações | 
| tipo_evento	| taxonomy	| Missa, Confissão, etc | 

# Relacionamentos

``` mathematica
Comunidade 1 ─── N Eventos
Capela N ─── 1 Paróquia (parent_paroquia)
```

# API REST
## Base
``` swift
/wp-json/mapa/v1/
```

## Listar Comunidades para o Mapa
``` bash
GET /comunidades
```

## Parâmetros suportados

| Campo | Tipo | Descrição |
|------|------|-----------|
| dia | integer ou string | [0 domingo - 6 sábado] ou "hoje" | 
| tipo_evento | string | [missa, confissão ...] |
| tipo_comunidade | string | [paroquia, capela, independente] |
| lat | integer | coordenada geografica |
| lng | integer | coordenada geografica |
| raio | integer | Raio de distancia para a busca de comunidades, só funciona se possuir lat e lng |
| tag | string | [libras, tridentina, crianças ...] |
| limite | integer | Quantidade Maxima de comunidades retornadas pela api |
| proximidade | boolean | Ordenada pela maior proximidade do ponto latitude e longitude oferecidos |
