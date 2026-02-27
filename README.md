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

## Arquitetura orientada a contexto

| Pasta | Responsabilidade |
|---|---|
| `includes/core` | Infraestrutura base (helpers e bootstrap de suporte) |
| `includes/auth` | Login, OAuth e formulários de autenticação |
| `includes/communities` | Regras de comunidade (post types, taxonomias, APIs e shortcodes) |
| `includes/admin` | Recursos exclusivos para administração do WordPress |
| `includes/database` | Criação e manutenção de tabelas |
| `templates` | HTML separado da lógica de negócio |

### Estrutura de pastas

```text
wp-map/
├── assets/
├── includes/
│   ├── admin/
│   ├── auth/
│   ├── communities/
│   │   ├── api/
│   │   └── shortcodes/
│   ├── core/
│   └── database/
├── templates/
│   └── shortcodes/
├── cadastro-comunidades.php
├── README.md
└── readme.txt
```

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
| dia_semana | string | Domingo → Sábado |
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
| dia | integer ou string | [0 domingo - 6 sábado] ou "hoje" |
| tipo_evento | string | [missa, confissão ...] |
| tipo_comunidade | string | [paroquia, capela, independente] |
| lat | integer | coordenada geográfica |
| lng | integer | coordenada geográfica |
| raio | integer | Raio de distância para busca (depende de lat/lng) |
| tag | string | [libras, tridentina, crianças ...] |
| limite | integer | Quantidade máxima de comunidades retornadas |
| proximidade | boolean | Ordena pela maior proximidade |
