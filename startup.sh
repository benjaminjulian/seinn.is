#!/bin/bash
python3 gtfs.py
tmux new-session -d -s scrape
tmux send-keys -t scrape 'python3 scrape.py' C-m