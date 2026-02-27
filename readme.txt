=== Mapa de Igrejas ===
Contributors: Josué Santos, Eduardo Moura
Tags: igreja, mapa, missas, católico, paróquia
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin que cadastra igrejas e exibe um mapa interativo com seus endereços, horários de Missa e redes sociais.

== Description ==

O **Mapa de Igrejas** permite cadastrar informações de igrejas católicas (nome, endereço, latitude/longitude, horários de Missa e redes sociais) diretamente no WordPress.
Esses dados são exibidos em um mapa interativo baseado no [Leaflet](https://leafletjs.com/), integrado ao OpenStreetMap.

== Arquitetura orientada a contexto ==

A base do plugin foi organizada por contexto para facilitar manutenção e evolução:

* `includes/core` - Infraestrutura base.
* `includes/auth` - Login, OAuth e formulários.
* `includes/communities` - Regras de comunidade.
* `includes/admin` - Recursos apenas para admin WP.
* `includes/database` - Criação de tabelas.
* `templates` - HTML separado da lógica.

== Estrutura de pastas ==

* `assets/`
* `includes/admin/`
* `includes/auth/`
* `includes/communities/api/`
* `includes/communities/shortcodes/`
* `includes/core/`
* `includes/database/`
* `templates/shortcodes/`

== Credits ==

Inspirado no projeto [Católico Cristão](https://catolicocristao.github.io/).
Desenvolvido com [Leaflet](https://leafletjs.com/) e [OpenStreetMap](https://www.openstreetmap.org/).
