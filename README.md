# Psalm Plugin for REDAXO

### Installation

```
composer require --dev redaxo/psalm-plugin
vendor/bin/psalm-plugin enable redaxo/psalm-plugin
```

The command will add the plugin to `psalm.xml`:

```xml
<psalm>
    <!--  project configuration -->
    <plugins>
        <pluginClass class="Redaxo\PsalmPlugin\Plugin"/>
    </plugins>
</psalm>
```
