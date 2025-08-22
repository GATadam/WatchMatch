from glob import glob
import json
import os
import time
from dotenv import load_dotenv
import mysql.connector
import requests

#kell egy main, hogy a másik kódban meg tudjuk hívni
def main(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_TABLE_P, DB_TABLE_R, DB_TABLE_M, DB_TABLE_W2W, IMAGE_ORIGINAL_URL, JSON_FOLDER):
    for i in range(5):
        try:
            conn = mysql.connector.connect(
                host=DB_HOST,
                user=DB_USER,
                password=DB_PASS,
                database=DB_NAME
            )
            cursor = conn.cursor()
            break
        except mysql.connector.Error as err:
            print("Error connecting to database:", err)
            time.sleep(5)
    else:
        return

    cursor.execute(f"SELECT tmdb_id FROM {DB_TABLE_P}")
    provider_ids = [row[0] for row in cursor.fetchall()] 
    # amúgy tuple lenne, ha csak fetch all, ami felesleges extra lépés
    cursor.execute(f"SELECT iso_code FROM {DB_TABLE_R}")
    region_codes = [row[0] for row in cursor.fetchall()]

    # létrehozok egy átmeneti táblát, hogy a rendes W2W tábla addig is működjön
    cursor.execute(f"CREATE TEMPORARY TABLE temp_W2W LIKE {DB_TABLE_W2W}")

    for provider_id in provider_ids:
        for region_code in region_codes:
            response = requests.get(JSON_FOLDER + f"{region_code}_{provider_id}.json")
            cursor.execute(f"SELECT id FROM {DB_TABLE_P} WHERE tmdb_id = {provider_id}")
            db_p_id = cursor.fetchone()[0]
            cursor.execute(f"SELECT id FROM {DB_TABLE_R} WHERE iso_code = '{region_code}'")
            db_r_id = cursor.fetchone()[0]
            data = response.json()
            for movie in data.get("results", []):
                cursor.execute(f"SELECT id FROM {DB_TABLE_M} WHERE tmdb_id = {movie.get('id')}")
                db_m_id = cursor.fetchone()
                if db_m_id is None:
                    # cursor.execute(f"INSERT INTO {DB_TABLE_M} (tmdb_id, title, popularity, picture) VALUES ({movie.get('id')}, {movie.get('title')}, {movie.get('popularity')}, {IMAGE_ORIGINAL_URL + movie.get('poster_path')})")
                    cursor.execute(f"INSERT INTO {DB_TABLE_M} (tmdb_id, title, popularity, picture) VALUES (%s, %s, %s, %s)",
                        (
                            movie.get('id'),
                            movie.get('title'),
                            movie.get('popularity'),
                            IMAGE_ORIGINAL_URL + movie.get('poster_path')
                        )
                    )
                    # elméletileg a 2 insert közötta  különbség az, hogy ezt a második verziót értelmezi helyesen a mysql és tesz idézőjeleket a stringekhez
                    conn.commit()
                cursor.execute(f"INSERT INTO temp_W2W (provider_id, region_code, movie_id) VALUES ({db_p_id}, {db_r_id}, {db_m_id})")
                conn.commit()
    
    cursor.execute(f"DELETE FROM {DB_TABLE_W2W}")
    cursor.execute(f"INSERT INTO {DB_TABLE_W2W} (provider_id, region_code, movie_id) SELECT provider_id, region_code, movie_id FROM temp_W2W")
    cursor.execute("DROP TABLE temp_W2W")
    
    conn.commit()
    cursor.close()
    conn.close()


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

main(DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_P, TABLE_R, TABLE_M, TABLE_W2W, IMAGE_ORIGINAL_URL, JSON_FOLDER)