# HTTP client usage in PhpStorm

This project uses [PhpStorm's built-in HTTP Client](https://www.jetbrains.com/help/idea/http-client-in-product-code-editor.html) for API development and testing. 
HTTP requests are defined in `.http` files and can be executed directly from the IDE.

---

## File structure

```
tools/
│
└── http/
    ├── requests/
    │   ├── aggregated.http                   # Aggregated datasets / Aggregoitu data
    │   ├── cases.http                        # Cases / Asiat
    │   ├── decisionmaker-and-trustees.http   # Decisionmakers, trustees / Päättäjät, henkilöt
    │   ├── decisions.http                    # Decisions / Päätökset
    │   ├── meetings.http                     # Meetings / Kokoukset
    │   ├── organization.http                 # Organization / Organisaatio
    │   ├── records.http                      # Records / Dokumentit
    ├── http-client.env.json                  # Shared environment variables
    └── http-client.private.env.json          # Developer-specific or sensitive data
```

## How to set up

1. Get the API key for Ahjo proxy
   1. Log in to Decisions production [https://paatokset.hel.fi/en/user](https://paatokset.hel.fi/en/user) with Tunnistamo.
   2. Go to [your profile](https://paatokset.hel.fi/en/user) and click on "Key authentication" tab. 
   3. Generate a new API key by clicking "Generate new key" button.
2. Repeat the above steps for local environment.
3. Create a `http-client.private.env.json` file to `/tools/http/` and add the following data to it. Overwrite the `THE_GENERATED_API_KEY` values with the keys you just generated:
```json
{
  "prod": {
    "api_key": "THE_GENERATED_API_KEY"
  },
  "local": {
    "api_key": "THE_GENERATED_API_KEY"
  }
}
```

## How to use the HTTP Client

1. Open any `.http` file.
2. Choose the active environment (`prod` or `local`) from the dropdown at the top.
3. Position your cursor on the request you want to execute.
4. Click the `Run` link above the request (or press `Cmd + Enter`).

## Environments

Environment variables are defined in either:

- `http-client.env.json` — shared config
- `http-client.private.env.json` — developer-specific config, like API keys

---

## References

- [Documentation of Ahjo-proxy](https://helsinkisolutionoffice.atlassian.net/wiki/x/AYBh1QE)
- [JetBrains HTTP Client documentation](https://www.jetbrains.com/help/idea/http-client-in-product-code-editor.html)
