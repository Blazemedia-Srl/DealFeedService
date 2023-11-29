#!/bin/bash

file_path="/amazon-temp/"
base_url="https://assoc-datafeeds-eu.amazon.com/datafeed/"
userAgent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3"  # Esempio di User-Agent di Chrome su Windows 10

env_file=".env"

amazon_username=$(grep "^AMAZON_BLAZEMEDIA_USERNAME=" "$env_file" | cut -d '=' -f2 | tr -d '"[:space:]')
amazon_password=$(grep "^AMAZON_BLAZEMEDIA_PASSWORD=" "$env_file" | cut -d '=' -f2 | tr -d '"[:space:]')

# Verifica se la cartella esiste, altrimenti creala
if [ ! -d "$file_path" ]; then
    mkdir -p "$file_path"
    echo "Created directory: $file_path"
fi

echo "Amazon Username: $amazon_username"
echo "Amazon Password: $amazon_password"

# Scarica il file listFeeds con curl utilizzando le credenziali dell'utente Amazon
curl -o "${file_path}listFeeds" --digest -u "$amazon_username:$amazon_password" -L --user-agent "$userAgent" "{$base_url}listFeeds"

# Dichiarazione dell'array per i nomi e i percorsi dei file scaricati
downloaded_files=()

echo "List file downloaded"

# Esegui il parsing delle URL
for line in $(cat "${file_path}listFeeds");do
    # Prendo i link dai tag <a>
    urls_in_line=($(echo "$line" | grep -o "href='[^']*'" | awk -F"'" '{print $2}'))
    
    for url in "${urls_in_line[@]}"; do

        if [[ "$url" == *"style.css"* ]]; then
            echo "Skipping $url"
            continue
        fi
        if [[ "$url" == *"it_amazon_df_deals.csv.gz"* ]]; then
            echo "Skipping $url"
            continue
        fi

        # Scarica ciascuna URL nell'array utilizzando le credenziali dell'utente Amazon
        filename=$(echo "$url" | grep -oP 'filename=\K[^&]+')
        curl -o "${file_path}dealfeeds.csv.gz" --digest -u "$amazon_username:$amazon_password" -L --user-agent "$userAgent" "${base_url}$url"

        # Aggiungi il nome e il percorso del file scaricato all'array
        downloaded_files+=("${file_path}${filename}")
    done
done

# Stampare l'array dei file scaricati (opzionale)
printf '%s\n' "${downloaded_files[@]}"
