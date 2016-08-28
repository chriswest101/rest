How to use:

Authorise Endpoint

Call /authorise as a POST request passing with it the client id, client security code, username and password and it will return your token

Call /me as a GET request passing the token to receive a JSON array of client and user details

Call /deauthorise as a DELETE request to deauthorise the login session