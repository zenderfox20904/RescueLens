# Contributing

Thanks for helping with RescueLens.

1. Fork the repository and create a branch from `main`.
2. Follow [docs/SETUP.md](docs/SETUP.md) to run the API, web and app.
3. Keep changes focused. Add or update tests in `backend/tests`; run `php artisan test` and `npm run build` in `frontend`.
4. Open a pull request using the template and describe how you tested it.

## Rules for every change

* Never commit `.env` files, API keys, tokens or real passwords.
* Do not log images, passwords, tokens or precise locations.
* Never expose a reporter's precise location in public responses or UI.
* Never claim that a road or area is safe.
* Only show emergency numbers that are verified and cite their source.
* UI copy refers to the AI as "Meta AI".
* Keep the app usable in dark and light themes and in right to left languages.

By contributing you agree that your work is released under the [MIT License](LICENSE).
