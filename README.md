# ReCodEx API documentation in OpenAPI format (formerly called Swagger)

## How to run:
Start any web server in this directory, for example PHP:
    php -S 127.0.0.1:6500

Then run a web browser at `http://127.0.0.1:6500/index.html`. Here you go!

# Testing the API with Swagger UI

1. Visit our [Swagger UI instance](https://recodex.github.io/api/ui.html). It's 
   possible to change the API host by appending `?host=api.host:4242` to the 
   URL.
2. Obtain an access token, preferably using the `/login` API endpoint.
3. Click the Authorize button and fill in `Bearer yourAccessToken` to log in
