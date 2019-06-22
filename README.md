# growatt-proxy-and-upload
Growatt inverter proxy and upload scripts to PVOutput. 

Verkeer van de omvormer wordt verzonden door de Shine-s Wifi dongle in de omvormer. Die stuurt het standaard naar server.growatt.com en dan kun je daar ook de dashboards bekijken. Als je zelf ook je data wilt uploaden naar PvOutput en/of andere dingen moet je het verkeer omleiden. Dit kun je doen door in de Wifi stick het verkeer naar een eigen NUC of Raspberry Pi te sturen. Zelf heb ik dat adres in de Wifi stick hetzelfde gehouden maar het verkeer omgeleid door in de PiHole lokaal adres richting mijn NUC te sturen. 

1)Op de Nuc draait script run_proxy in een loop die met proxy.pl de binnenkomende berichten vanuit de omvormers afvangt. Output hiervan is een buffer txt bestand waarin het bericht wordt opgeslagen. Afhankelijk van de variabele inverter1_ID tm inverter3_ID kan het script waardes ontvangen voor drie omvormers. Outputfilename is in te stellen via de variabele inverter1_output_filename tm inverter3_output_filename. Gemiddeld komen er elke 5 minunten x01 x04 berichten berichten binnen. 

2)Op de NUC draait ook een script run_upload die het extract.php script draait. Deze bekijkt of er buffer bestanden staan om verwerkt te worden. Op basis van de paramater kan ook dit script overweg met drie omvormers. Check even de constanten bovenin het script. Na het decrypten en parsen 
upload het script de data naar PvOutput en daarna via MQTT. Via MQTT kunnen allerlei Domotica pakketten deze waardes oppakken. Zelf gebruik ik Home assistant die de waardes zichtbaar maakt voor mij. 


Zie:
https://www.vromans.org/johan/software/sw_growatt_wifi_protocol.html. voor inkomende berichtdata 01 04 en 01 03 bijvoorbeeld/ 
