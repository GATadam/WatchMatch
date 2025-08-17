import os
import time
import json
import glob
import requests
import mysql.connector
from dotenv import load_dotenv

print("Start time:", time.strftime("%Y-%m-%d %H:%M:%S"))

if os.path.exists(".env"):
    load_dotenv(".env")

DB_HOST = os.getenv("DB_HOST")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER_NAME")
DB_PASS = os.getenv("DB_PASSWORD")
TABLE_P = os.getenv("DB_TABLE_P")
TABLE_R = os.getenv("DB_TABLE_R")
TMDB_API_KEY = os.getenv("TMDB_API_KEY")

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

with open("ids.txt", "w") as f:
    f.write("Provider IDs:\n" + "\n".join(prov_ids) + "\n\nRegion Codes:\n" + "\n".join(reg_codes))

print("Provider IDs:", prov_ids)
print("Region Codes:", reg_codes)

movies_dir = "movies"
os.makedirs(movies_dir, exist_ok=True)

for f in glob.glob(f"{movies_dir}/*.*"):
    os.remove(f)

headers = {
    "Authorization": f"Bearer {TMDB_API_KEY}",
    "accept": "application/json"
}

for prov_id in prov_ids:
    for reg_code in reg_codes:
        print(f"Provider ID: {prov_id}, Region Code: {reg_code}")
        url = f"https://api.themoviedb.org/3/discover/movie"
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
            print(f"Error fetching page 1: {resp.text}")
            continue

        data = resp.json()
        total_pages = data.get("total_pages", 0)

        all_movies = []
        num = 0

        for i in range(1, total_pages + 1):
            params["page"] = i
            resp = requests.get(url, headers=headers, params=params)
            if resp.status_code != 200:
                print(f"Error page {i}: {resp.text}")
                continue

            page_data = resp.json()
            all_movies.extend(page_data.get("results", []))

            filename = f"{movies_dir}/movies_{reg_code}_{prov_id}_{num:04d}.json"
            with open(filename, "w", encoding="utf-8") as f:
                json.dump(page_data, f, indent=2, ensure_ascii=False)

            num += 1
            time.sleep(0.001)

        merged_file = f"{movies_dir}/movies_all_{reg_code}_{prov_id}.json"
        with open(merged_file, "w", encoding="utf-8") as f:
            json.dump({"results": all_movies}, f, indent=2, ensure_ascii=False)

        for f in glob.glob(f"{movies_dir}/movies_{reg_code}_{prov_id}_*.json"):
            os.remove(f)

print("Finish time:", time.strftime("%Y-%m-%d %H:%M:%S"))
