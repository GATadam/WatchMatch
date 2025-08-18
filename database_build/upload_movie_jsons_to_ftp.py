import os
import glob
import ftplib


def main(FTP_HOST, FTP_USER, FTP_PASS, movies_dir):
    for json_file in glob.glob(f"{movies_dir}/*.json"):
        with open(json_file, "rb") as f:
            ftp = ftplib.FTP(FTP_HOST)
            ftp.login(FTP_USER, FTP_PASS)
            ftp.cwd("www/watchmatch_jsons/movies")
            for f in ftp.nlst():
                ftp.delete(f)
            ftp.storbinary(f"STOR {os.path.basename(json_file)}", f)
            ftp.quit()
