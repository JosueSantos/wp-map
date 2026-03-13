# WP MAP — Cadastro Colaborativo de Igrejas Católicas

**Contributors:** Josué Santos, Eduardo Moura  
**Tags:** igreja, mapa, missas, católico, paróquia  
**Requires at least:** 5.0  
**Tested up to:** 6.6  
**Requires PHP:** 7.4  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress para cadastro de **Comunidades (Paróquias, Capelas, Associações)** e seus **Eventos (Missas, Confissões, etc)** com API pública para alimentar mapas, apps ou outros sites.

---

## Estrutura de Dados

### Post Types

#### Comunidade (`comunidade`)

Representa:
- Paróquia
- Capela
- Associação independente

Cada **Capela pertence a uma paróquia**.

#### Evento (`evento`)

Representa:
- Missa
- Confissão
- Adoração
- Evento Social
- Outros

Cada **Evento pertence a uma Comunidade**.

---

## Campos das Comunidades

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

## Campos dos Eventos
| Campo | Tipo | Descrição |
|------|------|-----------|
| comunidade_id | integer | Comunidade dona do evento |
| frequencia | string | semanal, mensal, numero_semana, anual |
| dia_semana | integer | 0 (Domingo) → 6 (Sábado), quando aplicável |
| dia_mes | integer | 1 → 31, quando aplicável |
| numero_semana | integer | 1 → 5, quando aplicável |
| mes | integer | 1 → 12, quando aplicável |
| horario | string | Hora do evento |
| descricao | string | Descrição |
| observacao | string | Observações |
| tipo_evento | taxonomy | Missa, Confissão, etc |

## Relacionamentos

```text
Comunidade 1 ─── N Eventos
Capela N ─── 1 Paróquia (parent_paroquia)
```

## API REST

### Base

```text
/wp-json/mapa/v1/
```

### Listar Comunidades para o Mapa

```bash
GET /comunidades
```

### Parâmetros suportados

| Campo | Tipo | Descrição |
|------|------|-----------|
| periodo | string | hoje, semana, data |
| data | string | Data no formato YYYY-MM-DD (obrigatória quando `periodo=data`) |
| tipo_evento | string | [missa, confissão ...] |
| tipo_comunidade | string | [paroquia, capela, independente] |
| lat | integer | coordenada geográfica |
| lng | integer | coordenada geográfica |
| raio | integer | Raio de distância para busca (depende de lat/lng) |
| tag | string | [libras, tridentina, crianças ...] |
| limite | integer | Quantidade máxima de comunidades retornadas |
| proximidade | boolean | Ordena pela maior proximidade |


## Atualizações recentes

- A página single de comunidade agora usa endereço com link clicável para o Google Maps (abre em nova aba), sem exibir latitude/longitude no bloco de informações rápidas.
- Botões de compartilhamento padronizados: WhatsApp em verde e Facebook em azul.
- Contatos passaram a ser exibidos como links clicáveis (telefone, WhatsApp, redes, site e e-mail), abrindo em nova aba quando aplicável.
- Em **Minha Conta**, os locais cadastrados e observados exibem **Ver detalhes** (single) e **Editar**.
- Na lista de observação de alterações foi adicionado **Ver detalhes** para cada local quando disponível.
- O mapa principal agora agrupa pinos próximos com `Leaflet.markercluster`.
- No cadastro de comunidades, o campo **Tipo do Local** prioriza **Capela** e **Igreja Matriz** no topo.
- No cadastro de contatos, os rótulos dos tipos agora iniciam com maiúscula (ex.: Telefone, Site).
