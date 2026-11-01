# Codex: настройка агентов Vega Studio

Проверено 22 октября 2026 года. Рабочая папка — корень Git-репозитория `-site_for_sites`, вложенный в `site_for_site`. Открывай именно его в VS Code либо начинай CLI из него.

## Окружение и подтверждённые возможности

- `codex` в PATH: `C:/Users/PC/.vscode/extensions/openai.chatgpt-26.51002.51308-win32-x64/bin/windows-x86_64/codex.exe`, версия `0.162.0-alpha.2`. Этот бинарник также найден среди запущенных процессов.
- Второе установленное расширение: `openai.chatgpt-26.51007.21434-win32-x64`, его CLI — `0.162.0-alpha.17.2`.
- Отдельный запущенный Codex Desktop: `C:/Users/PC/AppData/Local/OpenAI/Codex/bin/896a0166b774037a/codex.exe`, версия `0.163.0-alpha.10.2`. Его custom-agent discovery отдельно не тестировался.
- Пользовательская конфигурация: `C:/Users/PC/.codex/config.toml`; модель `gpt-6.1-sol`, reasoning `low`. Глобальный конфиг не изменялся. Локальные модели и пакеты не устанавливались.
- CLI сообщает `multi_agent = true` (stable). AGENTS.md, skills и MCP поддерживаются. Существующий родительский workspace доверенный.

Официальная документация описывает standalone TOML в `.codex/agents/` с `name`, `description`, `developer_instructions`. Созданы пять таких файлов. **Однако две проверенные версии CLI в своих тестовых сессиях предоставили только `collaboration.spawn_agent(task_name, message, fork_turns, model, reasoning_effort)`, без выбора custom type. Автоматическое применение TOML по имени не подтверждено.** Не следует считать название task_name доказательством загрузки профиля.

Рабочий проверенный вариант: главный Codex запускает настоящие обычные subagents и явно поручает каждому прочитать соответствующий TOML и применить его инструкции. Пять отдельных subagents действительно прочитали свои профили и провели разные проверки. Это делегирование с явной загрузкой инструкций, а не подтверждённое нативное custom-type discovery. Если будущая сессия предоставляет native custom types, используй их напрямую.

Все профили наследуют модель и reasoning родителя: поля `model` и `model_reasoning_effort` намеренно не заданы. При текущем пользовательском конфиге это `gpt-6.1-sol` / `low`; выбор в интерфейсе может изменить наследуемые значения. Отдельные LLM не скачиваются.

## Созданные файлы

| Файл | Назначение |
| --- | --- |
| `AGENTS.md` | Общие правила, инварианты и координация с режимом совместимости |
| `.codex/agents/frontend-reviewer.toml` | HTML/CSS/JS, responsive/mobile, a11y, UI/UX и performance |
| `.codex/agents/backend-reviewer.toml` | PHP/PDO/БД, каталог, корзина, заказы, admin и транзакции |
| `.codex/agents/security-reviewer.toml` | Auth, sessions, injection, секреты, webhook и exposure |
| `.codex/agents/telegram-reviewer.toml` | Support routing, Reply, handoff и duplicate updates |
| `.codex/agents/ai-reviewer.toml` | LLM/context/fallback, состояния и generation races |
| `.agents/skills/vega-regression-review/SKILL.md` | Один переиспользуемый workflow проверки затронутых областей |
| `docs/CODEX-AGENTS.md` | Этот отчёт, результаты и примеры запуска |

Все пути относительны корню репозитория. В AGENTS.md закреплены серверные цены/validation, prepared statements, запрет секретов в Git, сохранность чужих правок, запрет destructive Git, syntax checks, проверка страниц, Support-группа и однозначный Reply routing, запрет AI-ответов в human mode и проверка актуальности generation. Существующего AGENTS.md в проекте не было.

## MCP

`codex mcp list --json` показал включённые `cua_repl` и `node_repl`, выключенные `code-review` и `codex_app`. Существуют включённые browser/computer-use плагины. Playwright MCP под таким именем не обнаружен. MCP/плагины сохранены без изменений, новые не добавлялись. Возможности конкретного клиента могут отличаться; frontend использует доступные браузерные инструменты по их инструкциям. В smoke-тесте браузер не запускался.

## Проверка

1. Оба CLI запущены с `--strict-config exec -s read-only --json`. Ошибок TOML от CLI не было, но native custom-type selector в контексте тестовых сессий отсутствовал. В первой сессии shell также не смог запуститься из-за Windows sandbox `setup refresh had errors`.
2. Затем реально выполнены пять отдельных subagent-проверок с явным чтением профилей. Первые три — frontend/backend/Telegram, следующая группа — security/AI. Каждый вернул свои просмотренные файлы и наблюдения. Главный Codex перепроверил существенные выводы по исходникам.
3. Пять TOML разобраны стандартным Python `tomllib`; обязательные строки и соответствие имени проверены. Структура frontmatter/body skill проверена. Штатный `quick_validate.py` не запустился: в существующем Python отсутствует PyYAML; зависимости не устанавливались.
4. `git diff --check` проходит. PHP/JS приложения не менялись; runtime-тесты, БД, реальные Telegram/LLM вызовы и визуальные проверки не выполнялись.

### Объединённые наблюдения smoke-аудита

Это короткая статическая проверка специализаций, не полный аудит и не runtime-воспроизведение.

| Область | Наблюдение |
| --- | --- |
| Frontend | `web-chat.js:13,112–119,138`: сообщения admin и reduced-motion ответы обходят live-announcer при `aria-live="off"` у лога. Фактическое озвучивание скринридером не проверялось. `tariffs.php:512`: кнопке «×» не хватает описательного имени/контекста позиции. |
| Backend | `create-order.php:46–55`: невалидный/скалярный cart JSON не нормализуется перед foreach; возможен PHP warning. Создание заказа блокируется ошибками. Серверные цены и транзакции подтверждены чтением OrderService. |
| Security | `admin/chat-api.php:13`: проверяется admin_id, но не idle timeout через appAdminSessionActive. Пока PHP хранит старую сессию, прямой запрос с cookie и CSRF может выполнять действия после прикладного timeout. Это не вход без авторизации. |
| Telegram | `telegram-webhook.php:36–39`: HTTP 500 после ошибки заменяется на 200. `telegram-poll.php:691–700`: update помечается обработанным до обработчика, без освобождения маркера при исключении. Возможна потеря сообщения при ошибке обработки. Support routing проверяет группу, отправителя и Reply mapping. |
| AI | `services/AiAssistant.php:309–312`: фильтр цифр/валют не исключает неподтверждённые суммы и сроки словами. Появление такого ответа зависит от LLM, реальные запросы не выполнялись. `ChatService.php:149–151` повторно проверяет статус и generation key. |

Дополнительное продуктовое расхождение: `api/chat.php:81–82` создаёт новый bot conversation после closed, включая poll. Это предусмотрено существующим lifecycle-тестом; нельзя объявлять дефектом без согласования желаемого поведения. Общий запрет ответов в закрытом старом диалоге сохраняется.

Замечания не исправлялись. Ветка осталась `Sania`; исходная незакоммиченная правка `styles.css` сохранена. Коммитов, push и переключения веток не было.

## Использование в VS Code и CLI

Начни новую сессию Codex в корне `-site_for_sites`, чтобы заново загрузились AGENTS.md и repository skills. Полный перезапуск VS Code обычно не нужен; при отсутствии skill в списке перезагрузи окно расширения. Перезапуск сам по себе не гарантирует появление native custom-type selector.

Для ручного запуска одного специалиста используй шаблон:

> Запусти отдельного subagent для read-only проверки frontend. Если native frontend-reviewer доступен, выбери его. Иначе явно поручай обычному subagent прочитать .codex/agents/frontend-reviewer.toml и применять developer_instructions. Дождись результата с file:line. Ничего не исправляй.

Для остальных замени имя и файл на `backend-reviewer`, `security-reviewer`, `telegram-reviewer` или `ai-reviewer` соответственно. Это запрос на реальное делегирование, а не команда `/frontend-reviewer`, существование которой не подтверждено.

Для большой задачи:

> Разбери задачу, выбери нужных специалистов по AGENTS.md, делегируй независимые части реальным subagents. При недоступности native custom types явно загрузи соответствующие профили. Дождись всех результатов, разреши противоречия, самостоятельно проверь критичные выводы и затем внеси согласованные изменения. Задача: …

Для общего workflow: `$vega-regression-review проверь изменения в текущей ветке`. Если skill не отображается в текущем клиенте, попроси явно прочитать `.agents/skills/vega-regression-review/SKILL.md`.

В VS Code доступны настоящие subagents; рабочая совместимая схема проверена в текущем окружении. Автоматическая нативная загрузка пяти TOML в IDE остаётся неподтверждённой. Review-only инструкции не создают отдельную read-only песочницу: разрешения наследуются от родителя. Для технического запрета записи запускай весь аудит в read-only режиме, учитывая обнаруженную ошибку Windows-песочницы.

## Официальные источники

- [Subagents и формат custom agents](https://learn.chatgpt.com/docs/agent-configuration/subagents)
- [AGENTS.md](https://learn.chatgpt.com/docs/agent-configuration/agents-md)
- [Codex Skills](https://developers.openai.com/codex/skills/)
