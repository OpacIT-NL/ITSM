# Changelog

## Onuitgebracht

### Beveiliging

- De API en het beheer van API-tokens zijn tijdelijk uitgeschakeld in afwachting van een nieuw autorisatiemodel; bestaande tokens worden ingetrokken.
- Centrale CSRF-bescherming, veilige sessiecookies en HTTP-beveiligingsheaders zijn toegevoegd.
- Autorisatie voor zoeken, bijlagen, beheerdersrechten en taaklinks is aangescherpt.
- Uploads worden op inhoud en extensie gevalideerd; actieve webbestanden worden geweigerd.
- Interne fouten worden met een `trace_id` in de PHP/Apache errorlog vastgelegd zonder databasefouten aan gebruikers te tonen.

## v0.6.0

### Nieuw

- Configuratiebeheer toegevoegd aan Asset Management.
- Assets kunnen nu in meerdere klantgebonden configuraties worden opgenomen.
- Assets kunnen onbeperkt aan elkaar gekoppeld worden in een configuratieboom.
- Beheerders kunnen asset-verbindingstypes beheren via de Asset Management instellingen.
- Beheerders kunnen configuratiesjablonen beheren via de admin instellingen.
- Assetkaarten tonen nu de configuraties en configuratieboom waar het asset onderdeel van is.
- PHP API toegevoegd onder `/api/v1`, met bearer token authenticatie voor behandelaars.
- API tokens kunnen door beheerders worden aangemaakt en beheerd in de instellingen.
- Change activities ondersteunen nu commentaar.
- Change activity kaarten tonen een samenvatting van de gekoppelde change, zoals nummer, titel, object-id, categorie en subcategorie.
- UBM tickets ondersteunen nu commentaar.

### Verbeterd

- Tabellen in secure hebben een compactere layout gekregen.
- Tabellen openen records met dubbelklik in plaats van losse open-knoppen.
- Tabellen voor taken tonen een vaste preview onderaan het scherm met omschrijving en commentaar.
- Assets gebruiken de compactere tabelweergave zonder previewpaneel.
- Tabellen tonen maximaal 100 records per pagina en gebruiken daarna paginering.
- Change activities worden binnen de applicatie in een nieuw tabblad geopend, zodat de change open blijft.
- De change activity lijst is omgezet naar een overzichtelijke tabel met detailpreview.
- Nieuwe change activities openen nu ook als in-app tabblad.
- UBM tickets gebruiken nu dezelfde kaartlayout als incidents en changes.
- Nieuwe UBM tickets gebruiken dezelfde layout als bestaande UBM tickets.
- Formulieren gebruiken een tweekoloms layout zoals incidents en changes.
- De linkerkant van taakformulieren blijft vast staan terwijl rechts gescrold wordt.
- In-app tabbladen hebben een nieuwe vaste breedte, iconen en betere overflow-afhandeling.
- Tabbladen onthouden nu ook het actieve subtabblad, zoals activiteiten of subtaken.
- Nieuwsbeheer gebruikt in tabelpreviews nu de berichttekst in plaats van een omschrijving.
- Automatisch uitloggen vernietigt nu de sessie op dezelfde manier als handmatig uitloggen.

### Opgelost

- Conceptdata van gesloten, niet-opgeslagen wijzigingen blijft niet meer hangen bij het opnieuw openen van een nieuwe change.
- Concepten voor nieuwsberichten blijven bewaard bij wisselen tussen tabbladen.
- Asset Management pagina's tonen geen ruwe PHP-code meer in de interface.
- Instellingenknoppen openen waar nodig binnen hetzelfde in-app tabblad.
- De API token instellingen passen nu weer netjes binnen de container.
- De configuratieboom kijkt nu omhoog en omlaag door alle verbonden assets.
- De lege witruimte onder change activity pagina's is verwijderd.
- Checkboxen en radiobuttons in formulieren zijn teruggebracht naar normale grootte.
- UBM tickets tonen geen overbodige "Niet voor klant" optie meer.

### Database

- Nieuwe tabellen toegevoegd voor configuratiebeheer:
  - `itsm_am_configurationtemplates`
  - `itsm_am_connectiontypes`
  - `itsm_am_configurations`
  - `itsm_am_configurationassets`
  - `itsm_am_assetconnections`
- Nieuwe tabel toegevoegd voor API tokens:
  - `itsm_api_tokens`
- Nieuwe tabel toegevoegd voor commentaar op change activities:
  - `itsm_cm_changeactivitycomments`
