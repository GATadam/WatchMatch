import os
import time
import json
import glob
import requests
import mysql.connector
from dotenv import load_dotenv
import add_movies_to_db
import upload_movie_jsons_to_ftp

start_time = time.strftime("%Y-%m-%d %H:%M:%S")

if os.path.exists(".env"):
    load_dotenv(".env")

DB_HOST = os.getenv("DB_HOST")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER_NAME")
DB_PASS = os.getenv("DB_PASSWORD")
TABLE_P = os.getenv("DB_TABLE_P")
TABLE_R = os.getenv("DB_TABLE_R")
TABLE_M = os.getenv("DB_TABLE_M")
TABLE_W2W = os.getenv("DB_TABLE_W2W")
TMDB_API_KEY = os.getenv("TMDB_API_KEY")
FTP_HOST = os.getenv("FTP_HOST")
FTP_USER = os.getenv("FTP_USER")
FTP_PASS = os.getenv("FTP_PASSWORD")
JSON_FOLDER = os.getenv("JSON_FOLDER")
IMAGE_ORIGINAL_URL = os.getenv("IMAGE_ORIGINAL_URL")

try:
    conn = mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME
    )
    cursor = conn.cursor()
except Exception as e:
    print("Database connection failed:", e)
    exit(1)

cursor.execute(f"SELECT tmdb_id FROM {TABLE_P}")
prov_ids = [row[0] for row in cursor.fetchall()]

cursor.execute(f"SELECT iso_code FROM {TABLE_R}")
reg_codes = [row[0] for row in cursor.fetchall()]

cursor.close()
conn.close()

"""with open("ids.txt", "w") as f:
    f.write("Provider IDs:\n" + "\n".join(map(str, prov_ids)) + "\n\nRegion Codes:\n" + "\n".join(reg_codes))

print("Provider IDs:", prov_ids)
print("Region Codes:", reg_codes)"""

movies_dir = "movies"
os.makedirs(movies_dir, exist_ok=True)

for f in glob.glob(f"{movies_dir}/*.json"):
    os.remove(f)

headers = {
    "Authorization": f"Bearer {TMDB_API_KEY}",
    "accept": "application/json"
}

for prov_id in prov_ids:
    for reg_code in reg_codes:
        print(f"Provider ID: {prov_id}, Region Code: {reg_code}")
        url = "https://api.themoviedb.org/3/discover/movie"
        params = {
            "include_adult": "false",
            "include_video": "false",
            "language": "en-US",
            "sort_by": "popularity.desc",
            "watch_region": reg_code,
            "with_watch_providers": prov_id,
            "page": 1
        }

        resp = requests.get(url, headers=headers, params=params)
        if resp.status_code != 200:
            for j in range(5):
                resp = requests.get(url, headers=headers, params=params)
                if resp.status_code == 200:
                    break
                time.sleep(1)
            else:
                print(f"Error fetching page 1: {resp.text}")
                break # ha nem sikerül az 1.-t leszedni új próbálkozások után sem, a többi sem fog menni

        data = resp.json()
        total_pages = data.get("total_pages", 0)
        if total_pages > 500:
            total_pages = 500

        all_movies = []

        for i in range(1, total_pages + 1):
            params["page"] = i
            resp = requests.get(url, headers=headers, params=params)
            if resp.status_code != 200:
                for j in range(5):
                    resp = requests.get(url, headers=headers, params=params)
                    if resp.status_code == 200:
                        break
                    time.sleep(1)
                print(f"Error page {i}: {resp.text}")
                continue # itt nem break, mert ha közte nem sikerül 1 oldal, azért mehetünk tovább

            page_data = resp.json()
            all_movies.extend(page_data.get("results", []))

            """filename = f"{movies_dir}/movies_{reg_code}_{prov_id}_{i:04d}.json"
            with open(filename, "w", encoding="utf-8") as f:
                json.dump(page_data, f, indent=2, ensure_ascii=False)"""

            time.sleep(0.1)

        merged_file = f"{movies_dir}/movies_all_{reg_code}_{prov_id}.json"
        with open(merged_file, "w", encoding="utf-8") as f:
            json.dump({"results": all_movies}, f, indent=2, ensure_ascii=False)

        """for f in glob.glob(f"{movies_dir}/movies_{reg_code}_{prov_id}_*.json"):
            os.remove(f)"""


upload_movie_jsons_to_ftp.main(FTP_HOST, FTP_USER, FTP_PASS, movies_dir)

# a 200 GB-s tárhelyen szabadabban elfér, mint a VPS 40 GB-jén
for f in glob.glob(f"{movies_dir}/*.json"):
    os.remove(f)

add_movies_to_db.main(DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_P, TABLE_R, TABLE_M, TABLE_W2W, IMAGE_ORIGINAL_URL, JSON_FOLDER)

finish_time = time.strftime("%Y-%m-%d %H:%M:%S")

with open("time.txt", "w") as f:
    f.write(f"Start time: {start_time}\n")
    f.write(f"Finish time: {finish_time}\n")
    f.write(f"Elapsed time: {time.strftime('%H:%M:%S', time.gmtime(time.time() - start_time))}\n")