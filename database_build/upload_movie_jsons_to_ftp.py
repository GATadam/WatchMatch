import os
import glob
import ftplib
from dotenv import load_dotenv


def main(FTP_HOST, FTP_USER, FTP_PASS, movies_dir):
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.cwd("www/watchmatch_jsons/movies")
    for file in ftp.nlst():
        name = os.path.basename(file)
        if name not in ('.', '..') and not file.endswith('/'):
            try:
                ftp.delete(file)
            except ftplib.error_perm as e:
                print(f"Could not delete {file}: {e}", flush=True)
    for json_file in glob.glob(f"{movies_dir}/*.json"):
        with open(json_file, "rb") as f:
            ftp.storbinary(f"STOR {os.path.basename(json_file)}", f)
    ftp.quit()


"""if os.path.exists(".env"):
    load_dotenv(".env")
    
movies_dir = "movies"
os.makedirs(movies_dir, exist_ok=True)

FTP_HOST = os.getenv("FTP_HOST")
FTP_USER = os.getenv("FTP_USER")
FTP_PASS = os.getenv("FTP_PASSWORD")

main(FTP_HOST, FTP_USER, FTP_PASS, movies_dir)"""
