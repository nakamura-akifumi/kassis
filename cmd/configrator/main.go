package main

import (
	"bufio"
	"flag"
	"fmt"
	kassiscore "github.com/nakamura-akifumi/kassis"
	"os"
)

func main() {

	fmt.Printf("kassis configrator. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	//TODO: action指定形式にしたい
	generateDefaultConfigSet := flag.Bool("generate-default-configset", false, "generate default configset to current directory")
	setupSolr := flag.Bool("setup-solr", false, "setup solr schema")
	deleteAlldata := flag.Bool("delete-alldata", false, "delete all solr data")
	startSolr := flag.Bool("start-solr", false, "Start solr")
	stopSolr := flag.Bool("stop-solr", false, "Stop solr")
	startTika := flag.Bool("start-tika", false, "Start tika")
	stopTika := flag.Bool("stop-tika", false, "Stop tika")
	downloadapp := flag.Bool("download-app", false, "download apps")
	corename := flag.String("corename", "", "solr corename")
	flag.Parse()

	fmt.Println("generateDefaultConfigSet:", *generateDefaultConfigSet)
	fmt.Println("setupSolr:", *setupSolr)
	fmt.Println("startSolr:", *startSolr)
	fmt.Println("stopSolr:", *stopSolr)
	fmt.Println("startTika:", *startTika)
	fmt.Println("stopTika:", *stopTika)
	fmt.Println("deleteAlldata:", *deleteAlldata)
	fmt.Println("downloadapp:", *downloadapp)
	fmt.Println("corename:", *corename)

	if *generateDefaultConfigSet == true {
		kassiscore.GenerateDefaultConfigSet()
	} else if *setupSolr == true {
		err := kassiscore.SetupSolr(*corename)
		if err != nil {
			fmt.Println(err)
		}
	} else if *deleteAlldata == true {
		cfg := kassiscore.LoadConfig()

		fmt.Println("clear documents?(Y/N)")
		scanner := bufio.NewScanner(os.Stdin)
		for scanner.Scan() {
			if scanner.Text() == "Y" {
				break
			}
			if scanner.Text() == "N" {
				return
			}
		}
		err := kassiscore.SolrClearDocument(cfg.Solr.Serveruri, cfg.Solr.Corename)
		if err != nil {
			fmt.Println(err)
		}
	} else if *startSolr == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.StartSolr(cfg)
	} else if *stopSolr == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.StopSolr(cfg)
	} else if *startTika == true {
		cfg := kassiscore.LoadConfig()
		err := kassiscore.StartTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	} else if *stopTika == true {
		cfg := kassiscore.LoadConfig()
		err := kassiscore.StopTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	} else if *downloadapp == true {
		kassiscore.DownloadApps()
	} else {
		kassiscore.CheckConfigAndConnections()
	}

}
