# Doomsketch

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/4f9e5d1062264da9be78ec495daa28b7)](https://app.codacy.com/gh/uigormarshall/doomSketch?utm_source=github.com&utm_medium=referral&utm_content=uigormarshall/doomSketch&utm_campaign=Badge_Grade)

> Desafios de arte cronometrados, sem punição por faltar um dia.

Doomsketch é uma plataforma de **desafios de desenho diários** (no espírito do Inktober e do Lospec) com identidade visual indie/dark. Artistas criam jornadas diárias com prompts, definem paletas de cores restritas e podem **clonar e customizar** desafios criados por outras pessoas.

**Princípio central:** consistência sem punição. Faltar um dia **não** cancela o progresso — envios retroativos são sempre permitidos.

## Funcionalidades

- **Criador de desafios** — título, descrição e duração geram dinamicamente os inputs de prompt de cada dia.
- **Paleta restrita opcional** — paleta nomeada com cores hex (`#rrggbb`) e preview visual; clique copia o hex para a área de transferência.
- **Clonar e customizar** — duplica um desafio com todos os dias e a paleta de forma independente, te define como criador e permite editar tudo antes de iniciar. A árvore de clones fica rastreada via `original_challenge_id`.
- **Painel de batalha** — grid sequencial de dias estilo calendário; cada dia mostra a arte enviada (thumbnail) ou o prompt com área de upload.
- **Envios retroativos** — o status do desafio permanece `active` mesmo com dias em branco; envie a qualquer momento, sem bloqueio por sequência quebrada.
- **Feed e descoberta** — feed da página inicial e página de explorar com desafios públicos, criador, duração e amostra da paleta.
- **Social** — likes e comentários em desafios e em artes, follows entre usuários, notificações e perfis públicos.
- **Privacidade** — desafios `is_private` não aparecem em feeds/listagens; instâncias e artes ligadas a eles só são visíveis para o dono.
- **Internacionalização** — português (pt_BR) e inglês (en) com troca de idioma em sessão.
- **Autenticação completa** — login, registro, reset de senha, verificação de e-mail, 2FA (TOTP + recovery codes) e passkeys, via Laravel Fortify.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 · Laravel 13 |
| Frontend | Livewire 4 · Flux UI 2 · Alpine.js · Tailwind CSS 4 (Vite) |
| Auth | Laravel Fortify (2FA, passkeys) |
| Banco | SQLite (padrão) |
| Testes | Pest 4 |
| Lint | Laravel Pint |

## Como rodar

Pré-requisitos: **PHP 8.4+**, **Composer**, **Node 22+** e **npm**.

```bash
# Instala dependências, cria .env, gera a key, migra e builda o front
composer setup

# Sobe tudo de uma vez: servidor, queue, logs (pail) e vite
composer dev
```

A aplicação fica disponível em `http://localhost:8000`.

Por padrão o banco é SQLite — nenhum serviço externo é necessário. O `composer setup` já roda as migrations.

### Dados de exemplo

```bash
# Usuário de teste mínimo (test@example.com / password)
php artisan db:seed

# Conteúdo de demonstração: 50 usuários com desafios, artes e interações
php artisan db:seed --class=DemoSeeder
```

## Modelo de domínio

| Tabela | Papel |
|---|---|
| `users` | Padrão Laravel + `username`, `avatar_path`, `bio` |
| `challenges` | Template do desafio (criador, duração, privacidade, paleta opcional, `original_challenge_id`) |
| `challenge_days` | Prompts diários (`day_number`, `prompt_text`) |
| `user_challenges` | Instância de um desafio para um usuário (`start_date`, `status`: active/completed/abandoned) |
| `submissions` | Arte enviada para um dia (`image_path`, `caption`) |
| `*_likes` / `*_comments` | Likes e comentários em desafios e artes |
| `follows` / `notifications` | Grafo social e notificações |

Toda a lógica de início, envio e conclusão de desafios é centralizada no `App\Services\ChallengeService` (`startChallenge`, `submitArt`, `markCompleted`).

## Testes

```bash
php artisan test --compact      # roda a suíte (Pest)
composer test                   # config:clear + pint --test + testes
vendor/bin/pint                 # formata o código
```

## Configuração

Flags de feature ficam em `config/features.php` (controladas por env):

- `FEATURE_IMPORT_CHALLENGE_JSON` — habilita a importação de desafios via JSON.

## Licença

[MIT](LICENSE).
