# Release checklist

Publish a release only after the package meets these checks.

## Quality

- All tests pass on every supported PHP version.
- Laravel compatibility is tested for every version in `composer.json`.
- Pint passes.
- Fresh Laravel installation succeeds.
- Database migrations run on supported database versions.
- Driver limitations are documented.
- Supervisor actions are covered by authorization and allowlist tests.
- No credentials or real queue payloads are present in the repository.

## Documentation

- README installation steps work from a clean project.
- Configuration options have examples.
- Driver capability differences are clear.
- Supervisor setup and security steps are complete.
- Breaking changes are recorded in the changelog.

## Packagist

1. Confirm the package name `mertaydin/laravel-queue-monitor` is available or belongs to the project account.
2. Register the GitHub repository on Packagist.
3. Enable the Packagist GitHub update hook.
4. Create a signed-off stable version tag, such as `v1.0.0`.
5. Publish the matching GitHub release.
6. Verify the package appears on Packagist.
7. Test `composer require mertaydin/laravel-queue-monitor` in a clean Laravel application.

Do not publish a stable version while a required driver or a documented feature is incomplete. Use pre-release tags for public testing when needed.
