# Driving Compilers

This is the source code of the articles found at [https://fabiensanglard.net/dc](https://fabiensanglard.net/dc).

# How to develop

Start your own local instance of a php server.

```
$ php -S localhost:8080
```

See your changes in a web browser: [http://localhost:8080](http://localhost:8080).

# How to contribute

If you want to make a change to the illustration, you should change them in `illu_masters` and then use the `illu_masters/publish.sh` script which converts them from object to path. This is done so browser without Roboto font can see the illustration as intended. And of course, when you do that, you should have Roboto Mono installed on your machine when you run the script.
