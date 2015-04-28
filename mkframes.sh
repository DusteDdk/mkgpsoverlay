#!/bin/bash

if [ "$1" == "" ] || [ "$2" == "" ]
then
    echo "Usage: ./$0 DATAFILE VIDEOFILE"
    echo "      DATAFILE - The json file to create overlay from."
    echo "      VIDEOFILE - Output filename of overlay video. (ex, out.mp4)"
    exit 1
fi

if [ ! -f "$1" ]
then
  echo "Error: $1 not found."
  exit 2
fi

if [ -f "$2" ]
then
  echo "Error: $2 already exist, move file or chose another name."
  exit 3
fi

if [ ! -d gps_temp ]
then
    mkdir gps_temp
fi

echo "Creating points..."
php jsonToPlot.php "$1" > "gps_temp/plot"


if cd gps_temp
then
    echo "Creating plots..."
    gnuplot < plot
    echo "Compositing frames..."
    for F in pos-*
    do
        if [ `ps -aux | grep -i convert | wc -l` -lt 8 ]
        then
            convert gps_background.png $F -composite frame-$F &
        else
        
            convert gps_background.png $F -composite frame-$F
        fi
        #rm $F
    done
    echo "Creating video..."
    ffmpeg -framerate 5 -i frame-pos-%07d.png -c:v libx264 -preset slow -crf 2 -r 59.940060 -pix_fmt yuv420p "$2"
    echo "Done."
else
echo "cd fail"
fi

